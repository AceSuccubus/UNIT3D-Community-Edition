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

namespace App\Http\Controllers\Staff;

use App\Repositories\ChatRepository;

/**
 * @see \Tests\Todo\Feature\Http\Controllers\Staff\FlushControllerTest
 */
class FlushController extends \App\Http\Controllers\Controller
{
    /**
     * @var ChatRepository
     */
    private ChatRepository $chatRepository;

    /**
     * ChatController Constructor.
     *
     * @param \App\Repositories\ChatRepository $chatRepository
     */
    public function __construct(\App\Repositories\ChatRepository $chatRepository)
    {
        $this->chatRepository = $chatRepository;
    }

    /**
     * Flsuh All Old Peers From Database.
     *
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function peers()
    {
        $carbon = new \Carbon\Carbon();
        $peers = \App\Models\Peer::select(['id', 'info_hash', 'user_id', 'updated_at'])->where('updated_at', '<', $carbon->copy()->subHours(2)->toDateTimeString())->get();
        foreach ($peers as $peer) {
            $history = \App\Models\History::where('info_hash', '=', $peer->info_hash)->where('user_id', '=', $peer->user_id)->first();
            if ($history) {
                $history->active = false;
                $history->save();
            }
            $peer->delete();
        }

        return \redirect()->route('staff.dashboard.index')->withSuccess('Ghost Peers Have Been Flushed');
    }

    /**
     * Flush All Chat Messages.
     *
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function chat()
    {
        foreach (\App\Models\Message::all() as $message) {
            \broadcast(new \App\Events\MessageDeleted($message));
            $message->delete();
        }
        $this->chatRepository->systemMessage('Chatbox Has Been Flushed! :broom:');

        return \redirect()->route('staff.dashboard.index')->withSuccess('Chatbox Has Been Flushed');
    }
}
