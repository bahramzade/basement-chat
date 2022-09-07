<?php

declare(strict_types=1);

namespace Haemanthus\Basement\Actions;

use Haemanthus\Basement\Contracts\MarkPrivatesMessagesAsRead as MarkPrivatesMessagesAsReadContract;
use Haemanthus\Basement\Data\PrivateMessageData;
use Haemanthus\Basement\Facades\Basement;
use Haemanthus\Basement\Notifications\PrivateMessageRead;
use Illuminate\Support\Facades\Notification;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;

class MarkPrivatesMessagesAsRead implements MarkPrivatesMessagesAsReadContract
{
    /**
     * Mark given private messages as has been read.
     */
    public function markAsRead(DataCollection $privateMessages): DataCollection
    {
        Basement::newPrivateMessageModel()
            ->whereIn('id', $privateMessages->toCollection()->pluck('id'))
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        $this->notifySenders($privateMessages);

        return $privateMessages;
    }
    /**
     * Notify the sender that the receiver has read private messages.
     */
    protected function notifySenders(DataCollection $privateMessages): void
    {
        /** @var \Illuminate\Support\Collection<int,\Haemanthus\Basement\Data\PrivateMessageData> $collection */
        $collection = $privateMessages->toCollection();

        /** @var \Illuminate\Support\Collection<int,\Spatie\LaravelData\Lazy> $senders */
        $senders = $collection->unique('sender_id')->pluck('sender');

        $senders->each(static function (Lazy $sender) use ($collection): void {
            /** @var \Illuminate\Foundation\Auth\User&\Haemanthus\Basement\Contracts\User $user */
            $user = $sender->resolve();
            $ownedMessages = $collection->filter(static fn (PrivateMessageData $data): bool => $data->id === $user->id);

            Notification::send(
                notifiables: $user,
                notification: new PrivateMessageRead(sender: $user, privateMessages: $ownedMessages)
            );
        });
    }
}