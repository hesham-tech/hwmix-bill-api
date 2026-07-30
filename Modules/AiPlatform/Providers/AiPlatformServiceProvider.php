<?php

// مزود الخدمة الرئيسي لمنصة الذكاء الاصطناعي
namespace Modules\AiPlatform\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface;
use Modules\AiPlatform\Contracts\Engines\AgentEngineInterface;
use Modules\AiPlatform\Contracts\Engines\CostEngineInterface;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\Contracts\Engines\KnowledgeEngineInterface;
use Modules\AiPlatform\Contracts\Engines\MemoryEngineInterface;
use Modules\AiPlatform\Contracts\Engines\PolicyEngineInterface;
use Modules\AiPlatform\Contracts\Engines\PromptEngineInterface;
use Modules\AiPlatform\Contracts\Engines\WorkflowEngineInterface;
use Modules\AiPlatform\Contracts\Router\AiRouterInterface;
use Modules\AiPlatform\Contracts\Security\SecretVaultInterface;
use Modules\AiPlatform\Plugins\AiPlatformPluginRegistry;
use Modules\AiPlatform\Security\AesSecretVault;

class AiPlatformServiceProvider extends ServiceProvider
{
    /**
     * تسجيل خدمات المنصة في الـ Container
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-platform.php',
            'ai-platform'
        );

        // تسجيل Plugin Registry و Analyzer Registry كـ Singleton
        $this->app->singleton(AiPlatformPluginRegistry::class);
        $this->app->singleton(\Modules\AiPlatform\Engines\AnalysisEngine\AnalyzerRegistry::class);

        // تسجيل Secret Vault
        $this->app->singleton(SecretVaultInterface::class, AesSecretVault::class);

        // تسجيل الـ Router
        $this->app->singleton(AiRouterInterface::class, \Modules\AiPlatform\Router\AiRouter::class);

        // تسجيل Execution Engine
        $this->app->singleton(ExecutionEngineInterface::class, \Modules\AiPlatform\Engines\ExecutionEngine::class);

        // تسجيل باقي المحركات (Engines)
        $this->app->singleton(PolicyEngineInterface::class, \Modules\AiPlatform\Engines\PolicyEngine::class);
        $this->app->singleton(PromptEngineInterface::class, \Modules\AiPlatform\Engines\PromptEngine::class);
        $this->app->singleton(MemoryEngineInterface::class, \Modules\AiPlatform\Engines\MemoryEngine::class);
        $this->app->singleton(KnowledgeEngineInterface::class, \Modules\AiPlatform\Engines\KnowledgeEngine::class);
        $this->app->singleton(CostEngineInterface::class, \Modules\AiPlatform\Engines\CostEngine::class);
        $this->app->singleton(AgentEngineInterface::class, \Modules\AiPlatform\Engines\AgentEngine::class);
        $this->app->singleton(WorkflowEngineInterface::class, \Modules\AiPlatform\Engines\WorkflowEngine::class);
        $this->app->singleton(\Modules\AiPlatform\Contracts\Engines\AnalysisEngineInterface::class, \Modules\AiPlatform\Engines\AnalysisEngine::class);

        // تسجيل الـ Platform Facade accessor
        $this->app->singleton('ai-platform', function ($app) {
            return new \Modules\AiPlatform\AiPlatformManager(
                $app,
                $app->make(AiPlatformPluginRegistry::class)
            );
        });
    }

    /**
     * تشغيل خدمات المنصة بعد التسجيل
     */
    public function boot(): void
    {
        // تسجيل الـ Drivers الافتراضية في الـ Registry
        /** @var AiPlatformPluginRegistry $registry */
        $registry = $this->app->make(AiPlatformPluginRegistry::class);
        $registry->registerDriver(\Modules\AiPlatform\Drivers\GeminiDriver::class);
        $registry->registerDriver(\Modules\AiPlatform\Drivers\OpenAiDriver::class);

        // تسجيل المحللات الافتراضية في الـ AnalyzerRegistry
        /** @var \Modules\AiPlatform\Engines\AnalysisEngine\AnalyzerRegistry $analyzerRegistry */
        $analyzerRegistry = $this->app->make(\Modules\AiPlatform\Engines\AnalysisEngine\AnalyzerRegistry::class);
        $analyzerRegistry->register($this->app->make(\Modules\AiPlatform\Engines\AnalysisEngine\Analyzers\FinancialSmsAnalyzer::class));

        // نشر ملف الإعدادات
        $this->publishes([
            __DIR__ . '/../config/ai-platform.php' => config_path('ai-platform.php'),
        ], 'ai-platform-config');

        // نشر Migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ai-platform-migrations');

        // تحميل Migrations تلقائياً
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // تحميل Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // تحميل Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-platform');

        // تحميل Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\AiPlatform\Commands\ArchiveLogsCommand::class,
                \Modules\AiPlatform\Commands\HealthCheckCommand::class,
                \Modules\AiPlatform\Commands\RotateSecretsCommand::class,
            ]);
        }
    }
}
