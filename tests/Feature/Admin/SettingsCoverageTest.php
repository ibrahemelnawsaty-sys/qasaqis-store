<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\ManageStoreSettings;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Filament\Forms\Components\Field;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Settings coverage guard (constitution 0.8, docs/10 §315).
 *
 * The store owner must be able to edit EVERY seeded setting from the panel. Two keys
 * (`store_maps_url`, `shipping_note`) were seeded with real, visitor-facing values yet
 * had no field on any admin page — the owner saw a Google-Maps link on the site they
 * could not change. These tests make that class of gap fail loudly instead of rotting.
 *
 * The "seeded" side is read back FROM THE DATABASE after actually running the seeder,
 * never from a hand-copied list — a mirror list would drift with the seeder and the
 * test would pass while the gap reopened (anti-pattern 18).
 */
final class SettingsCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The gap this whole file exists to prevent: a key the seeder writes that the
     * settings page cannot edit.
     */
    public function test_every_seeded_setting_key_is_editable_from_the_panel(): void
    {
        $this->seed(SettingSeeder::class);

        // Ground truth: what the seeder really wrote. No migration inserts rows into
        // `settings`, so after RefreshDatabase these are exactly the seeded keys.
        $seeded = Setting::query()->pluck('key')->all();
        $editable = array_keys(ManageStoreSettings::MANAGED);

        $uneditable = array_values(array_diff($seeded, $editable));
        sort($uneditable);

        $this->assertSame(
            [],
            $uneditable,
            'Seeded but NOT editable from ManageStoreSettings: '.implode(', ', $uneditable)
            .'. Every seeded key needs a field on the settings page (constitution 0.8).',
        );
    }

    /**
     * The reverse direction: the page must not claim to manage a key nobody seeds,
     * which would leave a field that silently creates an orphan row.
     */
    public function test_every_editable_key_is_actually_seeded(): void
    {
        $this->seed(SettingSeeder::class);

        $seeded = Setting::query()->pluck('key')->all();
        $unseeded = array_values(array_diff(array_keys(ManageStoreSettings::MANAGED), $seeded));
        sort($unseeded);

        $this->assertSame(
            [],
            $unseeded,
            'Editable on the settings page but never seeded: '.implode(', ', $unseeded)
            .'. Add them to SettingSeeder so a fresh install has a baseline value.',
        );
    }

    /**
     * Registry membership is not editability. A key can sit in MANAGED with no field
     * rendered, in which case save() would wipe it to null on every save. Assert the
     * real form schema instead of trusting the constant.
     */
    public function test_every_editable_key_has_a_real_field_on_the_form(): void
    {
        $fields = $this->formFieldNames();

        foreach (array_keys(ManageStoreSettings::MANAGED) as $key) {
            $this->assertContains(
                $key,
                $fields,
                "«{$key}» is registered in ManageStoreSettings::MANAGED but has no field in form(); "
                .'the admin cannot actually edit it.',
            );
        }
    }

    /** Any field on the page must be a registered key, otherwise save() ignores it. */
    public function test_the_form_has_no_field_outside_the_registry(): void
    {
        foreach ($this->formFieldNames() as $name) {
            $this->assertArrayHasKey(
                $name,
                ManageStoreSettings::MANAGED,
                "The form renders «{$name}» but it is missing from MANAGED, so save() never persists it.",
            );
        }
    }

    /** Two fields bound to one key would make the later silently overwrite the earlier. */
    public function test_no_key_is_bound_to_two_fields(): void
    {
        $names = $this->formFieldNames();

        $this->assertSame(
            array_values(array_unique($names)),
            $names,
            'The settings form binds the same key to more than one field.',
        );
    }

    /**
     * Group/type drift between the seeder and the page is silent corruption: the page
     * rewrites both columns on every save, so a mismatch flips the seeded metadata.
     */
    public function test_seeded_group_and_type_match_the_page_registry(): void
    {
        $this->seed(SettingSeeder::class);

        foreach (Setting::all() as $row) {
            $this->assertArrayHasKey($row->key, ManageStoreSettings::MANAGED, "Unregistered key: {$row->key}");

            [$group, $type] = ManageStoreSettings::MANAGED[$row->key];

            $this->assertSame($group, $row->group, "Group mismatch for «{$row->key}».");
            $this->assertSame($type, $row->type, "Type mismatch for «{$row->key}».");
        }
    }

    /**
     * Constitution 0.4 / 1.1: the threshold ships EMPTY — empty means "no threshold".
     * Seeding any number here would be inventing a business figure nobody supplied.
     */
    public function test_free_shipping_threshold_is_seeded_empty_with_no_invented_amount(): void
    {
        $this->seed(SettingSeeder::class);

        $row = Setting::query()->where('key', 'free_shipping_threshold')->first();

        $this->assertNotNull($row, 'free_shipping_threshold must be seeded.');
        $this->assertSame('shipping', $row->group);
        $this->assertSame('string', $row->type);
        $this->assertSame('', (string) $row->value, 'The threshold must ship empty — no amount is invented.');
        $this->assertFalse($row->is_encrypted);
    }

    /**
     * The threshold is optional money. Blank must stay valid (it is the documented
     * "no threshold" state) and no upper bound may creep in — adding maxLength() here
     * would become `max_digits` on a numeric field and silently reject real amounts.
     */
    public function test_free_shipping_threshold_accepts_blank_and_any_positive_amount(): void
    {
        $rules = ['free_shipping_threshold' => $this->formField('free_shipping_threshold')->getValidationRules()];

        foreach ([null, '', '0', '350', '5000.50'] as $accepted) {
            $this->assertFalse(
                Validator::make(['free_shipping_threshold' => $accepted], $rules)->fails(),
                'The threshold must accept '.var_export($accepted, true).'.',
            );
        }

        foreach (['-1', 'مجانًا'] as $rejected) {
            $this->assertTrue(
                Validator::make(['free_shipping_threshold' => $rejected], $rules)->fails(),
                'The threshold must reject '.var_export($rejected, true).'.',
            );
        }
    }

    /** The previously-uneditable keys are now reachable from the panel. */
    public function test_the_two_previously_orphaned_keys_are_now_editable(): void
    {
        $fields = $this->formFieldNames();

        foreach (['store_maps_url', 'shipping_note'] as $key) {
            $this->assertArrayHasKey($key, ManageStoreSettings::MANAGED);
            $this->assertContains($key, $fields);
        }
    }

    /** Constitution 4.4: page access is gated server-side, not by hiding a menu item. */
    public function test_page_access_requires_the_settings_view_permission(): void
    {
        $this->seedPermissions();

        $this->actingAs(User::factory()->create()); // no roles, no permissions
        $this->assertFalse(ManageStoreSettings::canAccess());

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('settings.view');
        $this->actingAs($viewer);
        $this->assertTrue(ManageStoreSettings::canAccess());
    }

    /**
     * Constitution 4.4 / anti-pattern 13: hiding the save button is not access control.
     * Reading the settings must not imply writing them.
     */
    public function test_saving_is_refused_without_settings_general_edit(): void
    {
        $this->seedPermissions();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('settings.view'); // read-only: NOT settings.general.edit
        $this->actingAs($viewer);

        try {
            (new ManageStoreSettings)->save();
            $this->fail('save() must refuse a user lacking settings.general.edit.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /**
     * Every Field the page's form renders, sections flattened.
     *
     * @return array<int, Field>
     */
    private function formFieldList(): array
    {
        $page = new ManageStoreSettings;
        $form = $page->form(Form::make($page));

        return array_values(array_filter(
            $form->getFlatComponents(withHidden: true),
            static fn (object $component): bool => $component instanceof Field,
        ));
    }

    /** @return array<int, string> */
    private function formFieldNames(): array
    {
        return array_map(
            static fn (Field $field): string => $field->getName(),
            $this->formFieldList(),
        );
    }

    private function formField(string $name): Field
    {
        foreach ($this->formFieldList() as $field) {
            if ($field->getName() === $name) {
                return $field;
            }
        }

        $this->fail("The form has no «{$name}» field.");
    }

    private function seedPermissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
