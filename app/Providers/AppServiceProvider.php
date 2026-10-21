<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\ChatbotFaq;
use App\Models\Course;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Position;
use App\Models\Section;
use App\Models\StudentYear;
use App\Models\UploadedRequirement;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class,
            Department::class,
            DocumentType::class,
            DocumentRequirement::class,
            UploadedRequirement::class,
            DocumentRequest::class,
            Announcement::class,
            ChatbotFaq::class,
            Office::class,
            Position::class,
            Course::class,
            StudentYear::class,
            Section::class,
        ] as $model) {
            $model::observe(ActivityLogObserver::class);
        }
    }
}
