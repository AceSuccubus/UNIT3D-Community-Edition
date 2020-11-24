<?php

declare(strict_types=1);
/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Notifications;

use App\Models\Torrent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewUploadTip extends Notification implements ShouldQueue
{
    use Queueable;

    public string $type;

    public string $tipper;

    public Torrent $torrent;

    public $amount;

    /**
     * Create a new notification instance.
     *
     * @param string $type
     * @param string $tipper
     * @param $amount
     * @param Torrent $torrent
     */
    public function __construct(string $type, string $tipper, $amount, Torrent $torrent)
    {
        $this->type = $type;
        $this->tipper = $tipper;
        $this->torrent = $torrent;
        $this->amount = $amount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray()
    {
        $appurl = \config('app.url');

        return [
            'title' => $this->tipper.' Has Tipped You '.$this->amount.' BON For An Uploaded Torrent',
            'body'  => $this->tipper.' has tipped one of your Uploaded Torrents '.$this->torrent->name,
            'url'   => \sprintf('/torrents/%s', $this->torrent->id),
        ];
    }
}
