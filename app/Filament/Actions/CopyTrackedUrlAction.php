<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use JsonException;

/**
 * Hands the recruiter a public link, optionally tagged with a `utm_source` so
 * the resulting clicks and applications can be attributed to whichever channel
 * it was shared on.
 *
 * Works for any record whose public page is addressed by its `key` — the UUID
 * {@see \App\Models\Concerns\HasUniqueKey} generates — so a job page and a
 * referral link differ only by the route name.
 */
class CopyTrackedUrlAction
{
    /**
     * @param  string  $routeName  A route taking the record's `key`, e.g. `job.show`.
     */
    public static function make(string $routeName, string $name = 'copyUrl'): Action
    {
        return Action::make($name)
            ->label(__('tracked_url.action'))
            ->icon(Heroicon::OutlinedLink)
            ->modalHeading(__('tracked_url.heading'))
            ->modalDescription(__('tracked_url.description'))
            ->modalSubmitActionLabel(__('tracked_url.submit'))
            ->modalWidth(Width::Large)
            ->schema([
                Toggle::make('add_tracking')
                    ->label(__('tracked_url.add_tracking'))
                    ->helperText(__('tracked_url.add_tracking_helper'))
                    ->inline(false)
                    ->live(),
                TextInput::make('utm_source')
                    ->label(__('tracked_url.utm_source'))
                    ->helperText(__('tracked_url.utm_source_helper'))
                    ->placeholder(__('tracked_url.utm_source_placeholder'))
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => (bool) $get('add_tracking'))
                    ->visible(fn (Get $get): bool => (bool) $get('add_tracking')),
            ])
            ->action(function (array $data, Model $record, Component $livewire) use ($routeName): void {
                $url = self::url(
                    route($routeName, ['key' => (string) $record->getAttribute('key')]),
                    $data,
                );

                // Copying has to happen in the browser, so the action pushes a
                // one-off snippet instead of returning anything renderable.
                $livewire->js(self::copyToClipboardScript($url));

                Notification::make()
                    ->title(__('tracked_url.copied'))
                    ->body($url)
                    ->success()
                    ->send();
            });
    }

    /** @param array<string, mixed> $data */
    private static function url(string $baseUrl, array $data): string
    {
        $source = trim((string) ($data['utm_source'] ?? ''));

        if (! ($data['add_tracking'] ?? false) || $source === '') {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query(['utm_source' => $source]);
    }

    /**
     * `navigator.clipboard` is only available in a secure context, which rules it
     * out when the panel is opened over plain HTTP on a LAN address, so a
     * hidden-textarea copy is kept as a fallback.
     *
     * @throws JsonException
     */
    private static function copyToClipboardScript(string $url): string
    {
        $value = json_encode($url, JSON_THROW_ON_ERROR);

        return <<<JS
            (() => {
                const value = {$value};

                if (window.navigator.clipboard && window.isSecureContext) {
                    window.navigator.clipboard.writeText(value);

                    return;
                }

                const textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            })();
        JS;
    }
}
