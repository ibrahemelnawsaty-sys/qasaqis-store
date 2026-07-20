<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * مشغّل المهام عبر HTTP (بديل cron): بوّابة التوكن + استدعاء المُجدول.
 */
class TaskRunnerTest extends TestCase
{
    public function test_missing_token_config_disables_the_route(): void
    {
        config(['tasks.runner_token' => '']);

        $this->get('/tasks/run/anything')->assertNotFound();
    }

    public function test_wrong_token_is_not_found(): void
    {
        config(['tasks.runner_token' => 'the-real-secret-value']);

        $this->get('/tasks/run/wrong-token')->assertNotFound();
    }

    public function test_valid_token_runs_the_scheduler(): void
    {
        config(['tasks.runner_token' => 'the-real-secret-value']);

        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);

        $this->get('/tasks/run/the-real-secret-value')
            ->assertOk()
            ->assertSee('ok');
    }
}
