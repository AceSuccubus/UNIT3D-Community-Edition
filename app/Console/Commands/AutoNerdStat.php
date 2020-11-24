<?php
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

namespace App\Console\Commands;

use App\Models\Ban;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Models\Warning;
use App\Repositories\ChatRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
/**
 * @see \Tests\Todo\Unit\Console\Commands\AutoNerdStatTest
 */
class AutoNerdStat extends \Illuminate\Console\Command
{
    /**
     * @var ChatRepository
     */
    private $chatRepository;
    public function __construct(\App\Repositories\ChatRepository $chatRepository)
    {
        parent::__construct();
        $this->chatRepository = $chatRepository;
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:nerdstat';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically Posts Daily Nerd Stat To Shoutbox';
    /**
     * Execute the console command.
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function handle()
    {
        if (\config('chat.nerd_bot') == true) {
            // Site Birthday
            $bday = \config('other.birthdate');
            // Logins Count Last 24hours
            $logins = \App\Models\User::whereNotNull('last_login')->where('last_login', '>', \Carbon\Carbon::now()->subDay())->count();
            // Torrents Uploaded Count Last 24hours
            $uploads = \App\Models\Torrent::where('created_at', '>', \Carbon\Carbon::now()->subDay())->count();
            // New Users Count Last 24hours
            $users = \App\Models\User::where('created_at', '>', \Carbon\Carbon::now()->subDay())->count();
            // Top Banker
            $banker = \App\Models\User::latest('seedbonus')->first();
            // Most Snatched Torrent
            $snatched = \App\Models\Torrent::latest('times_completed')->first();
            // Most Seeded Torrent
            $seeded = \App\Models\Torrent::latest('seeders')->first();
            // Most Leeched Torrent
            $leeched = \App\Models\Torrent::latest('leechers')->first();
            // FL Torrents
            $fl = \App\Models\Torrent::where('free', '=', 1)->count();
            // DU Torrents
            $du = \App\Models\Torrent::where('doubleup', '=', 1)->count();
            // Peers Count
            $peers = \App\Models\Peer::count();
            // New User Bans Count Last 24hours
            $bans = \App\Models\Ban::whereNull('unban_reason')->whereNull('removed_at')->where('created_at', '>', \Carbon\Carbon::now()->subDay())->count();
            // Hit and Run Warning Issued In Last 24hours
            $warnings = \App\Models\Warning::where('created_at', '>', \Carbon\Carbon::now()->subDay())->count();
            // URL Helpers
            $banker_url = \href_profile($banker);
            $seeded_url = \href_torrent($seeded);
            $leeched_url = \href_torrent($leeched);
            $snatched_url = \href_torrent($snatched);
            // Select A Random Nerd Stat
            $statArray = [\sprintf('In The Last 24 Hours [color=#93c47d][b]%s[/b][/color] Unique Users Have Logged Into ', $logins) . \config('other.title') . '!', \sprintf('In The Last 24 Hours [color=#93c47d][b]%s[/b][/color] Torrents Have Been Uploaded To ', $uploads) . \config('other.title') . '!', \sprintf('In The Last 24 Hours [color=#93c47d][b]%s[/b][/color] Users Have Registered To ', $users) . \config('other.title') . '!', \sprintf('There Are Currently [color=#93c47d][b]%s[/b][/color] Freeleech Torrents On ', $fl) . \config('other.title') . '!', \sprintf('There Are Currently [color=#93c47d][b]%s[/b][/color] Double Upload Torrents On ', $du) . \config('other.title') . '!', \sprintf('Currently [url=%s]%s[/url] Is The Best Seeded Torrent On ', $seeded_url, $seeded->name) . \config('other.title') . '!', \sprintf('Currently [url=%s]%s[/url] Is The Most Leeched Torrent On ', $leeched_url, $leeched->name) . \config('other.title') . '!', \sprintf('Currently [url=%s]%s[/url] Is The Most Snatched Torrent On ', $snatched_url, $snatched->name) . \config('other.title') . '!', \sprintf('Currently [url=%s]%s[/url] Is The Top BON Holder On ', $banker_url, $banker->username) . \config('other.title') . '!', \sprintf('Currently There Are [color=#93c47d][b]%s[/b][/color] Peers On ', $peers) . \config('other.title') . '!', \sprintf('In The Last 24 Hours [color=#dd7e6b][b]%s[/b][/color] Users Have Been Banned From ', $bans) . \config('other.title') . '!', \sprintf('In The Last 24 Hours [color=#dd7e6b][b]%s[/b][/color] Hit and Run Warnings Have Been Issued On ', $warnings) . \config('other.title') . '!', \config('other.title') . \sprintf(' Birthday Is [b]%s[/b]!', $bday), \config('other.title') . ' Is King!'];
            $selected = \mt_rand(0, (\is_countable($statArray) ? \is_countable($statArray) ? \count($statArray) : 0 : 0) - 1);
            // Auto Shout Nerd Stat
            $this->chatRepository->systemMessage($statArray[$selected], 2);
        }
        $this->comment('Automated Nerd Stat Command Complete');
    }
}
