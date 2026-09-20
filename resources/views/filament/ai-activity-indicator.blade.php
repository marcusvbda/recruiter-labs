{{--
    Thin mount point for the persistent AI indicator. The Livewire component
    ({@see App\Livewire\AiActivityIndicator}) computes the state; this file
    only wires it into the topbar render hook with the tenant Filament has
    already resolved.
--}}
@livewire('ai-activity-indicator', ['company' => $company], key('rl-ai-activity-indicator-'.$company->getKey()))
