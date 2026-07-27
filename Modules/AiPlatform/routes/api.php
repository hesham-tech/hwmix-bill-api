<?php

// مسارات API للمنصة — /api/v1/ai/...
use Illuminate\Support\Facades\Route;

Route::prefix(config('ai-platform.api.prefix', 'api/v1/ai'))
    ->middleware(config('ai-platform.api.middleware', ['api', 'auth:sanctum']))
    ->name('ai.')
    ->group(function () {

        // Direct Capability
        Route::post('capability/{capability}', [\Modules\AiPlatform\Http\Controllers\CapabilityController::class, 'run'])
            ->name('capability.run');

        // Agents
        Route::post('agents/{agent}/chat', [\Modules\AiPlatform\Http\Controllers\AgentController::class, 'chat'])
            ->name('agents.chat');
        Route::post('agents/{agent}/conversations', [\Modules\AiPlatform\Http\Controllers\AgentController::class, 'createConversation'])
            ->name('agents.conversations.create');
        Route::get('conversations', [\Modules\AiPlatform\Http\Controllers\ConversationController::class, 'index'])
            ->name('conversations.index');
        Route::get('conversations/{ulid}', [\Modules\AiPlatform\Http\Controllers\ConversationController::class, 'show'])
            ->name('conversations.show');

        // Workflows
        Route::post('workflows/{workflow}/run', [\Modules\AiPlatform\Http\Controllers\WorkflowController::class, 'run'])
            ->name('workflows.run');
        Route::get('workflows/{ulid}/status', [\Modules\AiPlatform\Http\Controllers\WorkflowController::class, 'status'])
            ->name('workflows.status');

        // Tools
        Route::post('tools/{tool}/execute', [\Modules\AiPlatform\Http\Controllers\ToolController::class, 'execute'])
            ->name('tools.execute');

        // Usage & Reporting
        Route::get('usage', [\Modules\AiPlatform\Http\Controllers\UsageController::class, 'index'])
            ->name('usage.index');
        Route::get('usage/report', [\Modules\AiPlatform\Http\Controllers\UsageController::class, 'report'])
            ->name('usage.report');

        // Execution Status
        Route::get('executions/{ulid}', [\Modules\AiPlatform\Http\Controllers\ExecutionController::class, 'show'])
            ->name('executions.show');
        Route::delete('executions/{ulid}', [\Modules\AiPlatform\Http\Controllers\ExecutionController::class, 'cancel'])
            ->name('executions.cancel');

        // Admin REST APIs for Vue.js Frontend Dashboard
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('stats', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'stats'])->name('stats');
            Route::get('providers', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'providers'])->name('providers');
            Route::get('agents', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'agents'])->name('agents.index');
            Route::post('agents', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'storeAgent'])->name('agents.store');
            Route::put('agents/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'updateAgent'])->name('agents.update');
            Route::delete('agents/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'destroyAgent'])->name('agents.destroy');
            Route::patch('agents/{id}/toggle', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'toggleAgentActive'])->name('agents.toggle');

            Route::get('prompts', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'prompts'])->name('prompts.index');
            Route::post('prompts', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'storePrompt'])->name('prompts.store');
            Route::put('prompts/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'updatePrompt'])->name('prompts.update');
            Route::delete('prompts/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'destroyPrompt'])->name('prompts.destroy');
            Route::patch('prompts/{id}/toggle', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'togglePromptActive'])->name('prompts.toggle');
            Route::get('usage-report', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'usageReport'])->name('usage-report');
            Route::get('models', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'models'])->name('models.index');
            Route::post('models', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'storeModel'])->name('models.store');
            Route::put('models/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'updateModel'])->name('models.update');
            Route::delete('models/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'destroyModel'])->name('models.destroy');
            Route::patch('models/{id}/toggle', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'toggleModelActive'])->name('models.toggle');
            Route::get('accounts', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'accounts'])->name('accounts.index');
            Route::post('accounts', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'storeAccount'])->name('accounts.store');
            Route::put('accounts/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'updateAccount'])->name('accounts.update');
            Route::delete('accounts/{id}', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'destroyAccount'])->name('accounts.destroy');
            Route::patch('accounts/{id}/toggle', [\Modules\AiPlatform\Http\Controllers\Admin\DashboardApiController::class, 'toggleAccountActive'])->name('accounts.toggle');
        });
    });
