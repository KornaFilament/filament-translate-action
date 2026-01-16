<?php

namespace pxlrbt\FilamentTranslateAction\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Http\Client\Exception\RequestException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use pxlrbt\FilamentTranslateAction\Actions\TranslateModelAction;

class TranslateAction extends Action
{
    private static ?string $apiKey = null;

    private string $sourceLocale = 'en';

    private RequestException|ConnectionException $exception;

    public static function apiKey(string $key): void
    {
        static::$apiKey = $key;
    }

    public static function getDefaultName(): ?string
    {
        return 'translate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon('heroicon-o-language')
            ->label(__('filament-translate-action::action.label'))
            ->requiresConfirmation()
            ->hidden(function (Page $livewire, $action) {
                return $livewire->activeLocale == $action->sourceLocale;
            })
            ->action(function (Page $livewire, $record) {
                $sourceLocale = $this->sourceLocale;
                $targetLocale = $livewire->activeLocale;

                $translate = new TranslateModelAction(
                    sourceLocale: $sourceLocale,
                    targetLocale: $targetLocale,
                    apiKey: static::$apiKey,
                );

                /**
                 * @var Model $record
                 */
                try {
                    $record = $translate($record);
                } catch (RequestException|ConnectionException $e) {
                    $this->exception = $e;
                    $this->failure();

                    return;
                }

                $attributes = $record->toArray();

                foreach ($record->getTranslatableAttributes() as $field) {
                    $attributes[$field] = $record->getTranslation($field, $targetLocale);
                }

                $livewire->form->fill($attributes);
            });
    }

    public function sendFailureNotification(): static
    {
        $e = $this->exception;

        Notification::make()
            ->title(__('filament-translate-action::action.notifications.error.title'))
            ->body(match (true) {
                $e instanceof ConnectionException => __('filament-translate-action::action.notifications.connection-error'),
                $e instanceof RequestException => match ($e->response->status()) {
                    429 => __('filament-translate-action::action.notifications.429-error'),
                    456 => __('filament-translate-action::action.notifications.456-error'),
                    default => __('filament-translate-action::action.notifications.error.body', [
                        'reason' => $e->response->reason(),
                    ]),
                }
            })
            ->danger()
            ->send();

        return $this;
    }

    public function sendSuccessNotification(): static
    {
        Notification::make()
            ->title(__('filament-translate-action::action.notifications.success.title'))
            ->body(__('filament-translate-action::action.notifications.success.body', [
                'fields' => count($this->getRecord()->getDirty()),
            ]))
            ->success()
            ->send();

        return $this;
    }

    public function sourceLocale(string $locale): static
    {
        $this->sourceLocale = $locale;

        return $this;
    }
}
