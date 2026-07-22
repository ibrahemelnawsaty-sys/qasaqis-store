<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تحليل SEO المباشر في محرّر المقال — يتحقّق من ربط الحقول المختلفة عن الكتاب
 * (المقتطف = الوصف، المحتوى = النصّ) وحفظ الكلمة المفتاحية.
 */
class ArticleSeoAnalysisTest extends TestCase
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

    public function test_editor_renders_analysis_using_excerpt_and_content(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'أفضل قصص أطفال لتعليم القيم قبل النوم',
                'excerpt' => 'دليل مختصر لاختيار قصص أطفال هادفة تُنمّي القيم والخيال لدى الصغار قبل النوم بلغة بسيطة.',
                'focus_keyword' => 'قصص أطفال',
                'slug' => 'best-kids-stories',
            ])
            ->assertSee('التقييم العام')
            ->assertSee('الكلمة المفتاحية المختارة')
            ->assertDontSee('غير موجودة في العنوان');
    }

    public function test_focus_keyword_persists_on_the_article(): void
    {
        $article = Article::create([
            'title' => 'مقال',
            'slug' => 'article-kw',
            'excerpt' => 'مقتطف',
            'content' => 'محتوى',
            'author_name' => 'كاتب',
            'category' => 'عام',
            'focus_keyword' => 'قصص أطفال',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'focus_keyword' => 'قصص أطفال',
        ]);
    }
}
