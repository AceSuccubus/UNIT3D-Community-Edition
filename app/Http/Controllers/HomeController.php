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

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\User;

/**
 * @see \Tests\Todo\Feature\Http\Controllers\Staff\HomeControllerTest
 */
class HomeController extends \App\Http\Controllers\Controller
{
    /**
     * Display Home Page.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(\Illuminate\Http\Request $request)
    {
        // For Cache
        $current = \Carbon\Carbon::now();
        $expiresAt = $current->addMinutes(1);
        // Authorized User
        $user = $request->user();
        // Latest Articles/News Block
        $articles = \cache()->remember('latest_article', $expiresAt, fn () => \App\Models\Article::latest()->take(1)->get());
        foreach ($articles as $article) {
            $article->newNews = $user->updated_at->subDays(3)->getTimestamp() < $article->created_at->getTimestamp() ? 1 : 0;
        }
        // Latest Torrents Block
        $personalFreeleech = \App\Models\PersonalFreeleech::where('user_id', '=', $user->id)->first();
        $newest = \cache()->remember('newest_torrents', $expiresAt, fn () => \App\Models\Torrent::with(['user', 'category', 'type'])->withCount(['thanks', 'comments'])->latest()->take(5)->get());
        $seeded = \cache()->remember('seeded_torrents', $expiresAt, fn () => \App\Models\Torrent::with(['user', 'category', 'type'])->withCount(['thanks', 'comments'])->latest('seeders')->take(5)->get());
        $leeched = \cache()->remember('leeched_torrents', $expiresAt, fn () => \App\Models\Torrent::with(['user', 'category', 'type'])->withCount(['thanks', 'comments'])->latest('leechers')->take(5)->get());
        $dying = \cache()->remember('dying_torrents', $expiresAt, fn () => \App\Models\Torrent::with(['user', 'category', 'type'])->withCount(['thanks', 'comments'])->where('seeders', '=', 1)->where('times_completed', '>=', 1)->latest('leechers')->take(5)->get());
        $dead = \cache()->remember('dead_torrents', $expiresAt, fn () => \App\Models\Torrent::with(['user', 'category', 'type'])->withCount(['thanks', 'comments'])->where('seeders', '=', 0)->latest('leechers')->take(5)->get());
        // Latest Topics Block
        $topics = \cache()->remember('latest_topics', $expiresAt, fn () => \App\Models\Topic::with('forum')->latest()->take(5)->get());
        // Latest Posts Block
        $posts = \cache()->remember('latest_posts', $expiresAt, fn () => \App\Models\Post::with('topic', 'user')->latest()->take(5)->get());
        // Online Block
        $users = \cache()->remember('online_users', $expiresAt, fn () => \App\Models\User::with('group', 'privacy')->withCount(['warnings' => function (\Illuminate\Database\Eloquent\Builder $query) {
            $query->whereNotNull('torrent')->where('active', '1');
        }])->where('last_action', '>', \now()->subMinutes(5))->get());
        $groups = \cache()->remember('user-groups', $expiresAt, fn () => \App\Models\Group::select(['name', 'color', 'effect', 'icon'])->oldest('position')->get());
        // Featured Torrents Block
        $featured = \cache()->remember('latest_featured', $expiresAt, fn () => \App\Models\FeaturedTorrent::with('torrent')->get());
        // Latest Poll Block
        $poll = \cache()->remember('latest_poll', $expiresAt, fn () => \App\Models\Poll::latest()->first());
        // Top Uploaders Block
        $uploaders = \cache()->remember('top_uploaders', $expiresAt, fn () => \App\Models\Torrent::with('user')->select(\Illuminate\Support\Facades\DB::raw('user_id, count(*) as value'))->groupBy('user_id')->latest('value')->take(10)->get());
        $pastUploaders = \cache()->remember('month_uploaders', $expiresAt, fn () => \App\Models\Torrent::with('user')->where('created_at', '>', $current->copy()->subDays(30)->toDateTimeString())->select(\Illuminate\Support\Facades\DB::raw('user_id, count(*) as value'))->groupBy('user_id')->latest('value')->take(10)->get());
        $freeleechTokens = \App\Models\FreeleechToken::where('user_id', $user->id)->get();
        $bookmarks = \App\Models\Bookmark::where('user_id', $user->id)->get();

        return \view('home.index', ['user' => $user, 'personal_freeleech' => $personalFreeleech, 'users' => $users, 'groups' => $groups, 'articles' => $articles, 'newest' => $newest, 'seeded' => $seeded, 'dying' => $dying, 'leeched' => $leeched, 'dead' => $dead, 'topics' => $topics, 'posts' => $posts, 'featured' => $featured, 'poll' => $poll, 'uploaders' => $uploaders, 'past_uploaders' => $pastUploaders, 'freeleech_tokens' => $freeleechTokens, 'bookmarks' => $bookmarks]);
    }
}
