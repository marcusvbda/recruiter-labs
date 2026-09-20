<?php

namespace App\Livewire;

use App\Models\Company;
use App\Services\AiActivityService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The persistent AI indicator in the topbar and the AI Activity panel behind
 * it. It owns no state of its own: every render asks
 * {@see AiActivityService} what is really happening, so the indicator can
 * never drift from the operation rows it describes.
 *
 * It refreshes when the realtime driver pushes
 * {@see AiActivityService::Event} on this workspace's channel — never on a
 * timer. A poll here would both lie about freshness and run the aggregation
 * queries for every open tab forever.
 *
 * Mounted only from the panel render hook in `AdminPanelProvider`, which
 * already refuses to render it without a resolved tenant and an
 * authenticated user.
 */
class AiActivityIndicator extends Component
{
    public Company $company;

    public function mount(Company $company): void
    {
        $this->company = $company;
    }

    public function render(): View
    {
        return view('livewire.ai-activity-indicator', [
            'activity' => app(AiActivityService::class)->for($this->company)->toArray(),
            'channel' => AiActivityService::ChannelPrefix.$this->company->slug,
            'event' => AiActivityService::Event,
        ]);
    }
}
