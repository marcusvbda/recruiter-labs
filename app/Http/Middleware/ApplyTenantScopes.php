<?php

namespace App\Http\Middleware;

use App\Models\Application;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\Company;
use App\Models\Interview;
use App\Models\JobApplicationQuestion;
use App\Models\JobCriterion;
use App\Models\JobReviewAlert;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $applyCompanyScope = static function (Builder $query): void {
            $tenant = Filament::getTenant();

            if (! $tenant instanceof Company) {
                return;
            }

            $query->whereBelongsTo($tenant, 'company');
        };

        Application::addGlobalScope('tenant', $applyCompanyScope);
        ApplicationInterviewBriefItem::addGlobalScope('tenant', $applyCompanyScope);
        Interview::addGlobalScope('tenant', $applyCompanyScope);
        JobApplicationQuestion::addGlobalScope('tenant', $applyCompanyScope);
        JobCriterion::addGlobalScope('tenant', $applyCompanyScope);
        JobReviewAlert::addGlobalScope('tenant', $applyCompanyScope);

        return $next($request);
    }
}
