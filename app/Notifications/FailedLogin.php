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

use Carbon\Carbon;
use Illuminate\Notifications\Notification;

class FailedLogin extends \Illuminate\Notifications\Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;
    /**
     * The request IP address.
     *
     * @var string
     */
    public string $ip;
    /**
     * The Time.
     *
     * @var Carbon\Carbon
     */
    public $carbon;

    /**
     * Create a new notification instance.
     *
     * @param string $ip
     */
    public function __construct(string $ip)
    {
        $this->ip = $ip;
        $this->carbon = \Carbon\Carbon::now();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        return ['mail'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array
     */
    public function toArray()
    {
        return ['ip' => $this->ip, 'time' => $this->carbon];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail()
    {
        return (new \Illuminate\Notifications\Messages\MailMessage())->error()->subject(\trans('email.fail-login-subject'))->greeting(\trans('email.fail-login-greeting'))->line(\trans('email.fail-login-line1'))->line(\trans('email.fail-login-line2', ['ip' => $this->ip, 'host' => \gethostbyaddr($this->ip), 'time' => $this->carbon]));
    }
}
