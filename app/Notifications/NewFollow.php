<?php
/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D is open-sourced software licensed under the GNU General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 * @author     HDVinnie, singularity43
 */

namespace App\Notifications;

use App\Follow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewFollow extends Notification implements ShouldQueue
{
    use Queueable;

    public $type;
    public $sender;
    public $follow;

    /**
     * Create a new notification instance.
     *
     * @param Follow $follow
     *
     * @return void
     */
    public function __construct(string $type, string $sender, Follow $follow)
    {
        $this->type = $type;
        $this->follow = $follow;
        $this->sender = $sender;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function toArray($notifiable)
    {
        $appurl = config('app.url');

        return [
            'title' => $this->sender.' Has Followed You!',
            'body'  => $this->sender.' has started to follow you so they will get notifications about your activities.',
            'url'   => "/{$this->follow->user->username}.{$this->follow->user->id}",
        ];
    }
}