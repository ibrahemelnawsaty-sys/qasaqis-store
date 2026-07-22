<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\BookResource\Pages\CreateBook;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تحليل SEO المباشر في محرّر الكتاب (نظير Yoast): النقاط تنعكس على الكلمة المفتاحية،
 * والكلمة تُحفَظ في العمود.
 */
class BookSeoAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Http::fake();
    }

    private function actAsAdmin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_editor_renders_live_analysis_reflecting_the_keyword(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'قصص أطفال ممتعة ومفيدة تُنمّي الخيال لدى الصغار',
                'short_description' => 'مجموعة قصص أطفال مصوّرة تعلّم القيم والأخلاق بأسلوب مشوّق ولغة بسيطة تناسب مرحلة ما قبل المدرسة.',
                'focus_keyword' => 'قصص أطفال',
                'slug' => 'kids-stories',
            ])
            ->assertSee('التقييم العام')
            ->assertSee('الكلمة المفتاحية المختارة')
            ->assertDontSee('غير موجودة في العنوان'); // الكلمة موجودة فعلًا في العنوان
    }

    public function test_editor_flags_keyword_missing_from_title(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'كتاب بلا الكلمة',
                'short_description' => 'وصف قصير.',
                'focus_keyword' => 'زرافة',
            ])
            ->assertSee('غير موجودة في العنوان');
    }

    public function test_focus_keyword_persists_on_the_book(): void
    {
        $book = Book::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'focus_keyword' => 'قصص أطفال',
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'focus_keyword' => 'قصص أطفال',
        ]);
    }
}
