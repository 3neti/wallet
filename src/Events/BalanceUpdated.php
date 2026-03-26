<?php

namespace LBHurtado\Wallet\Events;

use Bavix\Wallet\Internal\Events\BalanceUpdatedEventInterface;
use Bavix\Wallet\Internal\Exceptions\ExceptionInterface;
use Bavix\Wallet\Models\Wallet;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a wallet balance is updated.
 */
final class BalanceUpdated implements BalanceUpdatedEventInterface, ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Wallet  $wallet  The wallet whose balance was updated.
     * @param  DateTimeImmutable  $updatedAt  The timestamp of the balance update.
     */
    public function __construct(
        private Wallet $wallet,
        private DateTimeImmutable $updatedAt
    ) {}

    /**
     * Get the wallet ID.
     *
     * @return int The unique identifier of the wallet.
     */
    public function getWalletId(): int
    {
        return $this->wallet->getKey();
    }

    /**
     * Get the wallet UUID.
     *
     * @return string The UUID of the wallet.
     */
    public function getWalletUuid(): string
    {
        return $this->wallet->uuid;
    }

    /**
     * Get the wallet balance in integer format.
     *
     * @return string The wallet balance as an integer.
     */
    public function getBalance(): string
    {
        return $this->wallet->balanceInt;
    }

    /**
     * Get the wallet balance in float format.
     *
     * @return float The wallet balance as a float.
     */
    public function getBalanceFloat(): float
    {
        return $this->wallet->balanceFloat;
    }

    /**
     * Get the timestamp of the balance update.
     *
     * @return DateTimeImmutable The date and time of the update.
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->wallet->holder->id),
        ];
    }

    /**
     * Get the event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'balance.updated';
    }

    /**
     * Get the data to broadcast with the event.
     *
     * @return array<string, mixed>
     *
     * @throws ExceptionInterface
     */
    public function broadcastWith(): array
    {
        $this->wallet->refreshBalance();

        return [
            'walletId' => $this->getWalletId(),
            'balanceFloat' => $this->getBalanceFloat(),
            'updatedAt' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
            'message' => 'Balance updated.',
        ];
    }
}
