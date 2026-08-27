{{--
    Thin mount point for the floating activation launcher. The Livewire
    component ({@see App\Livewire\WorkspaceActivationLauncher}) decides
    whether it has anything to show; this file only wires it into the render
    hook with the tenant Filament already resolved.
--}}
@livewire('workspace-activation-launcher', ['company' => $company], key('rl-workspace-activation-launcher-'.$company->getKey()))
