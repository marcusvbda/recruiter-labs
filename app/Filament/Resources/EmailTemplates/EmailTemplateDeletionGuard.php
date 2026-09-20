<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Models\EmailTemplate;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * A template a pipeline stage is configured to send may not be deleted: the
 * `statuses.email_template_id` foreign key restricts it at the database level,
 * and this turns that restriction into an explanation naming the stages that
 * still depend on it, wherever the delete action is offered.
 */
class EmailTemplateDeletionGuard
{
    public static function halt(Action $action, EmailTemplate $template): void
    {
        $statuses = Status::query()
            ->where('email_template_id', $template->getKey())
            ->with('pipeline')
            ->get();

        if ($statuses->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(__('email_templates.notifications.in_use_title'))
            ->body(__('email_templates.notifications.in_use_body', [
                'stages' => $statuses
                    ->map(fn (Status $status): string => trim($status->pipeline->name.' · '.$status->name, ' ·'))
                    ->implode(', '),
            ]))
            ->danger()
            ->send();

        $action->halt();
    }
}
