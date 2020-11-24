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

use App\Models\Category;
use App\Models\FreeleechToken;
use App\Models\History;
use App\Models\Torrent;
use App\Models\TorrentFile;
use App\Models\User;
use App\Repositories\ChatRepository;
use App\Repositories\TorrentFacetedRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @see \Tests\Todo\Feature\Http\Controllers\TorrentControllerTest
 */
class TorrentController extends \App\Http\Controllers\Controller
{
    /**
     * @var TorrentFacetedRepository
     */
    private TorrentFacetedRepository $torrentFacetedRepository;
    /**
     * @var ChatRepository
     */
    private ChatRepository $chatRepository;
    /**
     * @var int
     */
    private const PAGE = 0;
    /**
     * @var string
     */
    private const SORTING = 'bumped_at';
    /**
     * @var int
     */
    private const DIRECTION = 2;
    /**
     * @var string
     */
    private const ORDER = 'desc';
    /**
     * @var int
     */
    private const QTY = 25;

    /**
     * RequestController Constructor.
     *
     * @param \App\Repositories\TorrentFacetedRepository $torrentFacetedRepository
     * @param \App\Repositories\ChatRepository           $chatRepository
     */
    public function __construct(\App\Repositories\TorrentFacetedRepository $torrentFacetedRepository, \App\Repositories\ChatRepository $chatRepository)
    {
        $this->torrentFacetedRepository = $torrentFacetedRepository;
        $this->chatRepository = $chatRepository;
    }

    /**
     * Displays Torrent List View.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function torrents(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $repository = $this->torrentFacetedRepository;
        $torrents = \App\Models\Torrent::with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments'])->orderBy('sticky', 'desc')->orderBy('bumped_at', 'desc')->paginate(25);
        $personalFreeleech = \App\Models\PersonalFreeleech::where('user_id', '=', $user->id)->first();
        $bookmarks = \App\Models\Bookmark::where('user_id', $user->id)->get();

        return \view('torrent.torrents', ['personal_freeleech' => $personalFreeleech, 'repository' => $repository, 'bookmarks' => $bookmarks, 'torrents' => $torrents, 'user' => $user, 'sorting' => '', 'direction' => 1, 'links' => null]);
    }

    /**
     * Torrent Similar Results.
     *
     * @param \Illuminate\Http\Request $request
     * @param $category_id
     * @param $tmdb
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function similar(\Illuminate\Http\Request $request, $categoryId, $tmdb)
    {
        $user = $request->user();
        $personalFreeleech = \App\Models\PersonalFreeleech::where('user_id', '=', $user->id)->first();
        $torrents = \App\Models\Torrent::with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments'])->where('category_id', '=', $categoryId)->where('tmdb', '=', $tmdb)->get()->sortByDesc('name');
        if (! $torrents || $torrents->count() < 1) {
            \abort(404, 'No Similar Torrents Found');
        }

        return \view('torrent.similar', ['user' => $user, 'personal_freeleech' => $personalFreeleech, 'torrents' => $torrents, 'tmdb' => $tmdb]);
    }

    /**
     * Displays Torrent Cards View.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \ErrorException
     * @throws \HttpInvalidParamException
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function cardLayout(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $torrents = \App\Models\Torrent::with(['user:id,username', 'category', 'type', 'resolution'])->latest()->paginate(33);
        $repository = $this->torrentFacetedRepository;
        foreach ($torrents as $torrent) {
            $meta = null;
            if ($torrent->category->tv_meta && ($torrent->tmdb || $torrent->tmdb != 0)) {
                $meta = \App\Models\Tv::with('genres', 'networks', 'seasons')->where('id', '=', $torrent->tmdb)->first();
            }
            if ($torrent->category->movie_meta && ($torrent->tmdb || $torrent->tmdb != 0)) {
                $meta = \App\Models\Movie::with('genres', 'cast', 'companies', 'collection')->where('id', '=', $torrent->tmdb)->first();
            }
            if ($torrent->category->game_meta && ($torrent->igdb || $torrent->igdb != 0)) {
                $meta = \MarcReichel\IGDBLaravel\Models\Game::with(['cover' => ['url', 'image_id'], 'artworks' => ['url', 'image_id'], 'genres' => ['name']])->find($torrent->igdb);
            }
            if ($meta) {
                $torrent->meta = $meta;
            }
        }

        return \view('torrent.cards', ['user' => $user, 'torrents' => $torrents, 'repository' => $repository]);
    }

    /**
     * Torrent Filter Remember Setting.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function filtered(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        if ($user && $request->has('force')) {
            if ($request->input('force') == 1) {
                $user->torrent_filters = 0;
                $user->save();
            } elseif ($request->input('force') == 2) {
                $user->torrent_filters = 1;
                $user->save();
            }
        }
    }

    /**
     * Torrent Grouping.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     *@throws \HttpInvalidParamException
     * @throws \Throwable
     *
     * @throws \ErrorException
     */
    public function groupingLayout(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $repository = $this->torrentFacetedRepository;
        $personalFreeleech = \App\Models\PersonalFreeleech::where('user_id', '=', $user->id)->first();
        $logger = null;
        $cache = [];
        $attributes = [];
        $builder = \Illuminate\Support\Facades\DB::table('torrents')->selectRaw('distinct(torrents.imdb),max(torrents.bumped_at) as sbumped_at,max(torrents.seeders) as sseeders,max(torrents.leechers) as sleechers,max(torrents.times_completed) as stimes_completed,max(torrents.name) as sname,max(torrents.size) as ssize')->leftJoin('torrents as torrentsl', 'torrents.id', '=', 'torrentsl.id')->groupBy('torrents.imdb')->whereRaw('torrents.status = ? AND torrents.imdb != ?', [1, 0]);
        $prelauncher = $builder->orderBy('s'.self::SORTING, self::ORDER)->pluck('imdb')->toArray();
        if (! \is_array($prelauncher)) {
            $prelauncher = [];
        }
        $lengthAwarePaginator = new \Illuminate\Pagination\LengthAwarePaginator($prelauncher, \count($prelauncher), self::QTY);
        $hungry = \array_chunk($prelauncher, self::QTY);
        $fed = [];
        if (\is_array($hungry) && \array_key_exists(self::PAGE, $hungry)) {
            $fed = $hungry[self::PAGE];
        }
        $totals = [];
        $counts = [];
        $launcher = \App\Models\Torrent::with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments'])->whereIn('imdb', $fed)->orderBy(self::SORTING, self::ORDER);
        foreach ($launcher->cursor() as $chunk) {
            if ($chunk->imdb) {
                $totals[$chunk->imdb] = ! \array_key_exists($chunk->imdb, $totals) ? 1 : $totals[$chunk->imdb] + 1;
                if (! \array_key_exists('imdb'.$chunk->imdb, $cache)) {
                    $cache['imdb'.$chunk->imdb] = [];
                }
                if (! \array_key_exists('imdb'.$chunk->imdb, $counts)) {
                    $counts['imdb'.$chunk->imdb] = 0;
                }
                if (! \array_key_exists('imdb'.$chunk->imdb, $attributes)) {
                    $attributes['imdb'.$chunk->imdb]['seeders'] = 0;
                    $attributes['imdb'.$chunk->imdb]['leechers'] = 0;
                    $attributes['imdb'.$chunk->imdb]['times_completed'] = 0;
                    $attributes['imdb'.$chunk->imdb]['types'] = [];
                    $attributes['imdb'.$chunk->imdb]['categories'] = [];
                    $attributes['imdb'.$chunk->imdb]['genres'] = [];
                }
                $attributes['imdb'.$chunk->imdb]['times_completed'] += $chunk->times_completed;
                $attributes['imdb'.$chunk->imdb]['seeders'] += $chunk->seeders;
                $attributes['imdb'.$chunk->imdb]['leechers'] += $chunk->leechers;
                if (! \array_key_exists($chunk->type_id, $attributes['imdb'.$chunk->imdb])) {
                    $attributes['imdb'.$chunk->imdb]['types'][$chunk->type_id] = $chunk->type_id;
                }
                if (! \array_key_exists($chunk->category_id, $attributes['imdb'.$chunk->imdb])) {
                    $attributes['imdb'.$chunk->imdb]['categories'][$chunk->category_id] = $chunk->category_id;
                }
                $cache['imdb'.$chunk->imdb]['torrent'.$counts['imdb'.$chunk->imdb]] = ['bumped_at' => $chunk->bumped_at, 'seeders' => $chunk->seeders, 'leechers' => $chunk->leechers, 'name' => $chunk->name, 'times_completed' => $chunk->times_completed, 'size' => $chunk->size, 'chunk' => $chunk];
                $counts['imdb'.$chunk->imdb]++;
            }
        }
        $torrents = \count($cache) > 0 ? $cache : null;
        if (\is_array($torrents)) {
            foreach ($torrents as $k1 => $c) {
                foreach ($c as $k2 => $d) {
                    $meta = null;
                    if ($d['chunk']->category->tv_meta && ($d['chunk']->tmdb || $d['chunk']->tmdb != 0)) {
                        $meta = \App\Models\Tv::with('genres', 'networks', 'seasons')->where('id', '=', $d['chunk']->tmdb)->first();
                    }
                    if ($d['chunk']->category->movie_meta && ($d['chunk']->tmdb || $d['chunk']->tmdb != 0)) {
                        $meta = \App\Models\Movie::with('genres', 'cast', 'companies', 'collection')->where('id', '=', $d['chunk']->tmdb)->first();
                    }
                    if ($d['chunk']->category->game_meta && ($d['chunk']->igdb || $d['chunk']->igdb != 0)) {
                        $meta = \MarcReichel\IGDBLaravel\Models\Game::with(['cover' => ['url', 'image_id'], 'artworks' => ['url', 'image_id'], 'genres' => ['name']])->find($d['chunk']->igdb);
                    }
                    if ($meta) {
                        $d['chunk']->meta = $meta;
                    }
                }
            }
        }
        $bookmarks = \App\Models\Bookmark::where('user_id', $user->id)->get();

        return \view('torrent.groupings', ['torrents' => $torrents, 'user' => $user, 'sorting' => self::SORTING, 'direction' => self::DIRECTION, 'links' => $lengthAwarePaginator, 'totals' => $totals, 'personal_freeleech' => $personalFreeleech, 'repository' => $repository, 'attributes' => $attributes, 'bookmarks' => $bookmarks])->render();
    }

    /**
     * Uses Input's To Put Together A Search.
     *
     * @param \Illuminate\Http\Request $request
     * @param Torrent                  $torrent
     *
     * @throws \ErrorException
     * @throws \HttpInvalidParamException
     * @throws \Throwable
     *
     * @return array
     */
    public function faceted(\Illuminate\Http\Request $request, \App\Models\Torrent $torrent)
    {
        $user = $request->user();
        $repository = $this->torrentFacetedRepository;
        $personalFreeleech = \App\Models\PersonalFreeleech::where('user_id', '=', $user->id)->first();
        $collection = null;
        $history = null;
        $nohistory = null;
        $seedling = null;
        $notdownloaded = null;
        $downloaded = null;
        $leeching = null;
        $idling = null;
        if ($request->has('view') && $request->input('view') === 'group') {
            $collection = 1;
        }
        if ($request->has('notdownloaded') && $request->input('notdownloaded') != null) {
            $notdownloaded = 1;
            $nohistory = 1;
        }
        if ($request->has('seeding') && $request->input('seeding') != null) {
            $seedling = 1;
            $history = 1;
        }
        if ($request->has('downloaded') && $request->input('downloaded') != null) {
            $downloaded = 1;
            $history = 1;
        }
        if ($request->has('leeching') && $request->input('leeching') != null) {
            $leeching = 1;
            $history = 1;
        }
        if ($request->has('idling') && $request->input('idling') != null) {
            $idling = 1;
            $history = 1;
        }
        $search = $request->input('search');
        $description = $request->input('description');
        $words = self::parseKeywords($request->input('keywords'));
        $uploader = $request->input('uploader');
        $imdb = $request->input('imdb');
        $tvdb = $request->input('tvdb');
        $tmdb = $request->input('tmdb');
        $mal = $request->input('mal');
        $igdb = $request->input('igdb');
        $startYear = $request->input('start_year');
        $endYear = $request->input('end_year');
        $categories = $request->input('categories');
        $types = $request->input('types');
        $resolutions = $request->input('resolutions');
        $genres = $request->input('genres');
        $freeleech = $request->input('freeleech');
        $doubleupload = $request->input('doubleupload');
        $featured = $request->input('featured');
        $stream = $request->input('stream');
        $highspeed = $request->input('highspeed');
        $sd = $request->input('sd');
        $internal = $request->input('internal');
        $alive = $request->input('alive');
        $dying = $request->input('dying');
        $dead = $request->input('dead');
        $page = (int) $request->input('page');
        $totals = null;
        $links = null;
        $order = null;
        $sorting = null;
        $terms = \explode(' ', $search);
        $search = '';
        foreach ($terms as $term) {
            $search .= '%'.$term.'%';
        }
        $usernames = \explode(' ', $uploader);
        $uploader = null;
        foreach ($usernames as $username) {
            $uploader .= $username.'%';
        }
        $keywords = \explode(' ', $description);
        $description = '';
        foreach ($keywords as $keyword) {
            $description .= '%'.$keyword.'%';
        }
        if ($request->has('sorting') && $request->input('sorting') != null) {
            $sorting = $request->input('sorting');
        }
        if ($request->has('direction') && $request->input('direction') != null) {
            $order = $request->input('direction');
        }
        if (! $sorting || $sorting === null || ! $order || $order === null) {
            $sorting = 'bumped_at';
            $order = 'desc';
            // $order = 'asc';
        }
        $direction = $order === 'asc' ? 1 : 2;
        $qty = $request->has('qty') ? $request->input('qty') : 25;
        if ($collection == 1) {
            $torrent = \Illuminate\Support\Facades\DB::table('torrents')->selectRaw('distinct(torrents.imdb),max(torrents.bumped_at) as sbumped_at,max(torrents.seeders) as sseeders,max(torrents.leechers) as sleechers,max(torrents.times_completed) as stimes_completed,max(torrents.name) as sname,max(torrents.size) as ssize')->leftJoin('torrents as torrentsl', 'torrents.id', '=', 'torrentsl.id')->groupBy('torrents.imdb')->whereRaw('torrents.status = ? AND torrents.imdb != ?', [1, 0]);
            if ($request->has('search') && $request->input('search') != null) {
                $torrent->where(function ($query) use ($search) {
                    $query->where('torrentsl.name', 'like', $search);
                });
            }
            if ($request->has('description') && $request->input('description') != null) {
                $torrent->where(function ($query) use ($description) {
                    $query->where('torrentsl.description', 'like', $description)->orwhere('torrentsl.mediainfo', 'like', $description);
                });
            }
            if ($request->has('keywords') && $request->input('keywords') != null) {
                $keyword = \App\Models\Keyword::select(['torrent_id'])->whereIn('name', $words)->get();
                $torrent->whereIn('torrentsl.id', $keyword);
            }
            if ($request->has('uploader') && $request->input('uploader') != null) {
                $match = \App\Models\User::whereRaw('(username like ?)', [$uploader])->orderBy('username', 'ASC')->first();
                if (null === $match) {
                    return ['result' => [], 'count' => 0];
                }
                $torrent->where('torrentsl.user_id', '=', $match->id)->where('torrentsl.anon', '=', 0);
            }
            if ($request->has('imdb') && $request->input('imdb') != null) {
                $torrent->where('torrentsl.imdb', '=', \str_replace('tt', '', $imdb));
            }
            if ($request->has('tvdb') && $request->input('tvdb') != null) {
                $torrent->orWhere('torrentsl.tvdb', '=', $tvdb);
            }
            if ($request->has('tmdb') && $request->input('tmdb') != null) {
                $torrent->orWhere('torrentsl.tmdb', '=', $tmdb);
            }
            if ($request->has('mal') && $request->input('mal') != null) {
                $torrent->orWhere('torrentsl.mal', '=', $mal);
            }
            if ($request->has('igdb') && $request->input('igdb') != null) {
                $torrent->orWhere('torrentsl.igdb', '=', $igdb);
            }
            if ($request->has('start_year') && $request->has('end_year') && $request->input('start_year') != null && $request->input('end_year') != null) {
                $torrent->whereBetween('torrentsl.release_year', [$startYear, $endYear]);
            }
            if ($request->has('categories') && $request->input('categories') != null) {
                $torrent->whereIn('torrentsl.category_id', $categories);
            }
            if ($request->has('types') && $request->input('types') != null) {
                $torrent->whereIn('torrentsl.type_id', $types);
            }
            if ($request->has('resolutions') && $request->input('resolutions') != null) {
                $torrent->whereIn('torrentsl.resolution_id', $resolutions);
            }
            if ($request->has('genres') && $request->input('genres') != null) {
                $matches = \App\Models\GenreTorrent::select(['torrent_id'])->distinct()->whereIn('genre_id', $genres)->get();
                $torrent->whereIn('torrentsl.id', $matches);
            }
            if ($request->has('freeleech') && $request->input('freeleech') != null) {
                $torrent->where('torrentsl.free', '=', $freeleech);
            }
            if ($request->has('doubleupload') && $request->input('doubleupload') != null) {
                $torrent->where('torrentsl.doubleup', '=', $doubleupload);
            }
            if ($request->has('featured') && $request->input('featured') != null) {
                $torrent->where('torrentsl.featured', '=', $featured);
            }
            if ($request->has('stream') && $request->input('stream') != null) {
                $torrent->where('torrentsl.stream', '=', $stream);
            }
            if ($request->has('highspeed') && $request->input('highspeed') != null) {
                $torrent->where('torrentsl.highspeed', '=', $highspeed);
            }
            if ($request->has('sd') && $request->input('sd') != null) {
                $torrent->where('torrentsl.sd', '=', $sd);
            }
            if ($request->has('internal') && $request->input('internal') != null) {
                $torrent->where('torrentsl.internal', '=', $internal);
            }
            if ($request->has('alive') && $request->input('alive') != null) {
                $torrent->where('torrentsl.seeders', '>=', $alive);
            }
            if ($request->has('dying') && $request->input('dying') != null) {
                $torrent->where('torrentsl.seeders', '=', $dying)->where('torrentsl.times_completed', '>=', 3);
            }
            if ($request->has('dead') && $request->input('dead') != null) {
                $torrent->where('torrentsl.seeders', '=', $dead);
            }
        } elseif ($nohistory == 1) {
            $history = \App\Models\History::select(['torrents.id'])->leftJoin('torrents', 'torrents.info_hash', '=', 'history.info_hash')->where('history.user_id', '=', $user->id)->get()->toArray();
            if (! $history || ! \is_array($history)) {
                $history = [];
            }
            $torrent = $torrent->with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments'])->whereNotIn('torrents.id', $history);
        } elseif ($history == 1) {
            $torrent = \App\Models\History::where('history.user_id', '=', $user->id);
            $torrent->where(function ($query) use ($seedling, $downloaded, $leeching, $idling) {
                if ($seedling == 1) {
                    $query->orWhere(function ($query) {
                        $query->whereRaw('history.active = ? AND history.seeder = ?', [1, 1]);
                    });
                }
                if ($downloaded == 1) {
                    $query->orWhere(function ($query) {
                        $query->whereRaw('history.completed_at is not null');
                    });
                }
                if ($leeching == 1) {
                    $query->orWhere(function ($query) {
                        $query->whereRaw('history.active = ? AND history.seeder = ? AND history.completed_at is null', [1, 0]);
                    });
                }
                if ($idling == 1) {
                    $query->orWhere(function ($query) {
                        $query->whereRaw('history.active = ? AND history.seeder = ? AND history.completed_at is null', [0, 0]);
                    });
                }
            });
            $torrent = $torrent->selectRaw('distinct(torrents.id),max(torrents.sticky),max(torrents.bumped_at),max(torrents.seeders),max(torrents.leechers),max(torrents.name),max(torrents.size),max(torrents.times_completed)')->leftJoin('torrents', function ($join) {
                $join->on('history.info_hash', '=', 'torrents.info_hash');
            })->groupBy('torrents.id');
        } else {
            $torrent = $torrent->with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments']);
        }
        if ($collection != 1) {
            if ($request->has('search') && $request->input('search') != null) {
                $torrent->where(function ($query) use ($search) {
                    $query->where('torrents.name', 'like', $search);
                });
            }
            if ($request->has('description') && $request->input('description') != null) {
                $torrent->where(function ($query) use ($description) {
                    $query->where('torrents.description', 'like', $description)->orWhere('mediainfo', 'like', $description);
                });
            }
            if ($request->has('keywords') && $request->input('keywords') != null) {
                $keyword = \App\Models\Keyword::select(['torrent_id'])->whereIn('name', $words)->get();
                $torrent->whereIn('torrents.id', $keyword);
            }
            if ($request->has('uploader') && $request->input('uploader') != null) {
                $match = \App\Models\User::whereRaw('(username like ?)', [$uploader])->orderBy('username', 'ASC')->first();
                if (null === $match) {
                    return ['result' => [], 'count' => 0];
                }
                $torrent->where('torrents.user_id', '=', $match->id)->where('anon', '=', 0);
            }
            if ($request->has('imdb') && $request->input('imdb') != null) {
                $torrent->where('torrents.imdb', '=', \str_replace('tt', '', $imdb));
            }
            if ($request->has('tvdb') && $request->input('tvdb') != null) {
                $torrent->where('torrents.tvdb', '=', $tvdb);
            }
            if ($request->has('tmdb') && $request->input('tmdb') != null) {
                $torrent->where('torrents.tmdb', '=', $tmdb);
            }
            if ($request->has('mal') && $request->input('mal') != null) {
                $torrent->where('torrents.mal', '=', $mal);
            }
            if ($request->has('igdb') && $request->input('igdb') != null) {
                $torrent->where('torrents.igdb', '=', $igdb);
            }
            if ($request->has('start_year') && $request->has('end_year') && $request->input('start_year') != null && $request->input('end_year') != null) {
                $torrent->whereBetween('torrents.release_year', [$startYear, $endYear]);
            }
            if ($request->has('categories') && $request->input('categories') != null) {
                $torrent->whereIn('torrents.category_id', $categories);
            }
            if ($request->has('types') && $request->input('types') != null) {
                $torrent->whereIn('torrents.type_id', $types);
            }
            if ($request->has('resolutions') && $request->input('resolutions') != null) {
                $torrent->whereIn('torrents.resolution_id', $resolutions);
            }
            if ($request->has('genres') && $request->input('genres') != null) {
                $matches = \App\Models\GenreTorrent::select(['torrent_id'])->distinct()->whereIn('genre_id', $genres)->get();
                $torrent->whereIn('torrents.id', $matches);
            }
            if ($request->has('freeleech') && $request->input('freeleech') != null) {
                $torrent->where('torrents.free', '=', $freeleech);
            }
            if ($request->has('doubleupload') && $request->input('doubleupload') != null) {
                $torrent->where('torrents.doubleup', '=', $doubleupload);
            }
            if ($request->has('featured') && $request->input('featured') != null) {
                $torrent->where('torrents.featured', '=', $featured);
            }
            if ($request->has('stream') && $request->input('stream') != null) {
                $torrent->where('torrents.stream', '=', $stream);
            }
            if ($request->has('highspeed') && $request->input('highspeed') != null) {
                $torrent->where('torrents.highspeed', '=', $highspeed);
            }
            if ($request->has('sd') && $request->input('sd') != null) {
                $torrent->where('torrents.sd', '=', $sd);
            }
            if ($request->has('internal') && $request->input('internal') != null) {
                $torrent->where('torrents.internal', '=', $internal);
            }
            if ($request->has('alive') && $request->input('alive') != null) {
                $torrent->where('torrents.seeders', '>=', $alive);
            }
            if ($request->has('dying') && $request->input('dying') != null) {
                $torrent->where('torrents.seeders', '=', $dying)->where('times_completed', '>=', 3);
            }
            if ($request->has('dead') && $request->input('dead') != null) {
                $torrent->where('torrents.seeders', '=', $dead);
            }
        }
        $logger = null;
        $cache = [];
        $attributes = [];
        $links = null;
        if ($collection == 1) {
            if ($logger === null) {
                $logger = 'torrent.results_groupings';
            }
            $prelauncher = $torrent->orderBy('s'.$sorting, $order)->pluck('imdb')->toArray();
            if (! \is_array($prelauncher)) {
                $prelauncher = [];
            }
            $links = new \Illuminate\Pagination\LengthAwarePaginator($prelauncher, \count($prelauncher), $qty);
            $hungry = \array_chunk($prelauncher, $qty);
            $fed = [];
            if ($page < 1) {
                $page = 1;
            }
            if (\is_array($hungry) && \array_key_exists($page - 1, $hungry)) {
                $fed = $hungry[$page - 1];
            }
            $totals = [];
            $counts = [];
            $launcher = \App\Models\Torrent::with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments'])->whereIn('imdb', $fed)->orderBy($sorting, $order);
            foreach ($launcher->cursor() as $chunk) {
                if ($chunk->imdb) {
                    $totals[$chunk->imdb] = ! \array_key_exists($chunk->imdb, $totals) ? 1 : $totals[$chunk->imdb] + 1;
                    if (! \array_key_exists('imdb'.$chunk->imdb, $cache)) {
                        $cache['imdb'.$chunk->imdb] = [];
                    }
                    if (! \array_key_exists('imdb'.$chunk->imdb, $counts)) {
                        $counts['imdb'.$chunk->imdb] = 0;
                    }
                    if (! \array_key_exists('imdb'.$chunk->imdb, $attributes)) {
                        $attributes['imdb'.$chunk->imdb]['seeders'] = 0;
                        $attributes['imdb'.$chunk->imdb]['leechers'] = 0;
                        $attributes['imdb'.$chunk->imdb]['times_completed'] = 0;
                        $attributes['imdb'.$chunk->imdb]['types'] = [];
                        $attributes['imdb'.$chunk->imdb]['categories'] = [];
                        $attributes['imdb'.$chunk->imdb]['genres'] = [];
                    }
                    $attributes['imdb'.$chunk->imdb]['times_completed'] += $chunk->times_completed;
                    $attributes['imdb'.$chunk->imdb]['seeders'] += $chunk->seeders;
                    $attributes['imdb'.$chunk->imdb]['leechers'] += $chunk->leechers;
                    if (! \array_key_exists($chunk->type_id, $attributes['imdb'.$chunk->imdb])) {
                        $attributes['imdb'.$chunk->imdb]['types'][$chunk->type_id] = $chunk->type_id;
                    }
                    if (! \array_key_exists($chunk->category_id, $attributes['imdb'.$chunk->imdb])) {
                        $attributes['imdb'.$chunk->imdb]['categories'][$chunk->category_id] = $chunk->category_id;
                    }
                    $cache['imdb'.$chunk->imdb]['torrent'.$counts['imdb'.$chunk->imdb]] = ['bumped_at' => $chunk->bumped_at, 'seeders' => $chunk->seeders, 'leechers' => $chunk->leechers, 'name' => $chunk->name, 'times_completed' => $chunk->times_completed, 'size' => $chunk->size, 'chunk' => $chunk];
                    $counts['imdb'.$chunk->imdb]++;
                }
            }
            $torrents = \count($cache) > 0 ? $cache : null;
        } elseif ($history == 1) {
            $prelauncher = $torrent->orderBy('torrents.sticky', 'desc')->orderBy('torrents.'.$sorting, $order)->pluck('id')->toArray();
            if (! \is_array($prelauncher)) {
                $prelauncher = [];
            }
            $links = new \Illuminate\Pagination\LengthAwarePaginator($prelauncher, \count($prelauncher), $qty);
            $hungry = \array_chunk($prelauncher, $qty);
            $fed = [];
            if ($page < 1) {
                $page = 1;
            }
            if (\is_array($hungry) && \array_key_exists($page - 1, $hungry)) {
                $fed = $hungry[$page - 1];
            }
            $torrents = \App\Models\Torrent::with(['user:id,username', 'category', 'type', 'resolution'])->withCount(['thanks', 'comments'])->whereIn('id', $fed)->orderBy($sorting, $order)->get();
        } else {
            $torrents = $torrent->orderBy('sticky', 'desc')->orderBy($sorting, $order)->paginate($qty);
        }
        if ($collection == 1 && \is_array($torrents)) {
            foreach ($torrents as $k1 => $c) {
                foreach ($c as $k2 => $d) {
                    $meta = null;
                    if ($d['chunk']->category->tv_meta && ($d['chunk']->tmdb || $d['chunk']->tmdb != 0)) {
                        $meta = \App\Models\Tv::with('genres', 'networks', 'seasons')->where('id', '=', $d['chunk']->tmdb)->first();
                    }
                    if ($d['chunk']->category->movie_meta && ($d['chunk']->tmdb || $d['chunk']->tmdb != 0)) {
                        $meta = \App\Models\Movie::with('genres', 'cast', 'companies', 'collection')->where('id', '=', $d['chunk']->tmdb)->first();
                    }
                    if ($d['chunk']->category->game_meta && ($d['chunk']->igdb || $d['chunk']->igdb != 0)) {
                        $meta = \MarcReichel\IGDBLaravel\Models\Game::with(['cover' => ['url', 'image_id'], 'artworks' => ['url', 'image_id'], 'genres' => ['name']])->find($d['chunk']->igdb);
                    }
                    if ($meta) {
                        $d['chunk']->meta = $meta;
                    }
                }
            }
        }
        if ($request->has('view') && $request->input('view') === 'card') {
            if ($logger == null) {
                $logger = 'torrent.results_cards';
            }
            foreach ($torrents as $torrent) {
                $meta = null;
                if ($torrent->category->tv_meta && ($torrent->tmdb || $torrent->tmdb != 0)) {
                    $meta = \App\Models\Tv::with('genres')->where('id', '=', $torrent->tmdb)->first();
                }
                if ($torrent->category->movie_meta && ($torrent->tmdb || $torrent->tmdb != 0)) {
                    $meta = \App\Models\Movie::with('genres')->where('id', '=', $torrent->tmdb)->first();
                }
                if ($torrent->category->game_meta && ($torrent->igdb || $torrent->igdb != 0)) {
                    $meta = \MarcReichel\IGDBLaravel\Models\Game::with(['cover' => ['url', 'image_id'], 'artworks' => ['url', 'image_id'], 'genres' => ['name']])->find($torrent->igdb);
                }
                if ($meta) {
                    $torrent->meta = $meta;
                }
            }
        }
        if ($logger == null) {
            $logger = 'torrent.results';
        }
        $bookmarks = \App\Models\Bookmark::where('user_id', $user->id)->get();

        return \view($logger, ['torrents' => $torrents, 'user' => $user, 'personal_freeleech' => $personalFreeleech, 'sorting' => $sorting, 'direction' => $direction, 'links' => $links, 'totals' => $totals, 'repository' => $repository, 'attributes' => $attributes, 'bookmarks' => $bookmarks])->render();
    }

    /**
     * Anonymize A Torrent Media Info.
     *
     * @param $mediainfo
     *
     * @return array|void
     */
    private static function anonymizeMediainfo($mediainfo)
    {
        if ($mediainfo === null) {
            return;
        }
        $completeNameI = \strpos($mediainfo, 'Complete name');
        if ($completeNameI !== false) {
            $pathI = \strpos($mediainfo, ': ', $completeNameI);
            if ($pathI !== false) {
                $pathI += 2;
                $endI = \strpos($mediainfo, "\n", $pathI);
                $path = \substr($mediainfo, $pathI, $endI - $pathI);
                $newPath = \App\Helpers\MediaInfo::stripPath($path);

                return \substr_replace($mediainfo, $newPath, $pathI, \strlen($path));
            }
        }

        return $mediainfo;
    }

    /**
     * Parse Torrent Keywords.
     *
     * @param $text
     *
     * @return array
     */
    private static function parseKeywords($text)
    {
        $parts = \explode(', ', $text);
        $len = \count($parts);
        $result = [];
        foreach ($parts as $part) {
            $part = \trim($part);
            if ($part != '') {
                $result[] = $part;
            }
        }

        return $result;
    }

    /**
     * Display The Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \ErrorException
     * @throws \HttpInvalidParamException
     */
    public function torrent(\Illuminate\Http\Request $request, Torrent $id)
    {
        $torrent = \App\Models\Torrent::withAnyStatus()->with(['comments', 'category', 'type', 'resolution', 'subtitles'])->findOrFail($id);
        $uploader = $torrent->user;
        $user = $request->user();
        $freeleechToken = \App\Models\FreeleechToken::where('user_id', '=', $user->id)->where('torrent_id', '=', $torrent->id)->first();
        $personalFreeleech = \App\Models\PersonalFreeleech::where('user_id', '=', $user->id)->first();
        $comments = $torrent->comments()->latest()->paginate(5);
        $totalTips = \App\Models\BonTransactions::where('torrent_id', '=', $id)->sum('cost');
        $userTips = \App\Models\BonTransactions::where('torrent_id', '=', $id)->where('sender', '=', $request->user()->id)->sum('cost');
        $lastSeedActivity = \App\Models\History::where('info_hash', '=', $torrent->info_hash)->where('seeder', '=', 1)->latest('updated_at')->first();
        $meta = null;
        if ($torrent->category->tv_meta && $torrent->tmdb && $torrent->tmdb != 0) {
            $meta = \App\Models\Tv::with('genres', 'networks', 'seasons')->where('id', '=', $torrent->tmdb)->first();
        }
        if ($torrent->category->movie_meta && $torrent->tmdb && $torrent->tmdb != 0) {
            $meta = \App\Models\Movie::with('genres', 'cast', 'companies', 'collection')->where('id', '=', $torrent->tmdb)->first();
        }
        $characters = null;
        if ($torrent->category->game_meta) {
            if ($torrent->igdb || $torrent->igdb != 0) {
                $meta = \MarcReichel\IGDBLaravel\Models\Game::with(['cover' => ['url', 'image_id'], 'artworks' => ['url', 'image_id'], 'genres' => ['name']])->find($torrent->igdb);
                $characters = \MarcReichel\IGDBLaravel\Models\Character::whereIn('games', [$torrent->igdb])->take(6)->get();
            }
        }
        $featured = $torrent->featured == 1 ? \App\Models\FeaturedTorrent::where('torrent_id', '=', $id)->first() : null;
        $general = null;
        $video = null;
        $settings = null;
        $audio = null;
        $generalCrumbs = null;
        $textCrumbs = null;
        $subtitle = null;
        $viewCrumbs = null;
        $videoCrumbs = null;
        $settings = null;
        $audioCrumbs = null;
        $subtitle = null;
        $subtitleCrumbs = null;
        if ($torrent->mediainfo != null) {
            $mediaInfo = new \App\Helpers\MediaInfo();
            $parsed = $mediaInfo->parse($torrent->mediainfo);
            $viewCrumbs = $mediaInfo->prepareViewCrumbs($parsed);
            $general = $parsed['general'];
            $generalCrumbs = $viewCrumbs['general'];
            $video = $parsed['video'];
            $videoCrumbs = $viewCrumbs['video'];
            $settings = $parsed['video'] !== null && isset($parsed['video'][0]) && isset($parsed['video'][0]['encoding_settings']) ? $parsed['video'][0]['encoding_settings'] : null;
            $audio = $parsed['audio'];
            $audioCrumbs = $viewCrumbs['audio'];
            $subtitle = $parsed['text'];
            $textCrumbs = $viewCrumbs['text'];
        }
        $playlists = $user->playlists;

        return \view('torrent.torrent', ['torrent' => $torrent, 'comments' => $comments, 'user' => $user, 'personal_freeleech' => $personalFreeleech, 'freeleech_token' => $freeleechToken, 'meta' => $meta, 'characters' => $characters, 'total_tips' => $totalTips, 'user_tips' => $userTips, 'featured' => $featured, 'general' => $general, 'general_crumbs' => $generalCrumbs, 'video_crumbs' => $videoCrumbs, 'audio_crumbs' => $audioCrumbs, 'text_crumbs' => $textCrumbs, 'video' => $video, 'audio' => $audio, 'subtitle' => $subtitle, 'settings' => $settings, 'uploader' => $uploader, 'last_seed_activity' => $lastSeedActivity, 'playlists' => $playlists]);
    }

    /**
     * Torrent Edit Form.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function editForm(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        \abort_unless($user->group->is_modo || $user->id == $torrent->user_id, 403);

        return \view('torrent.edit_torrent', ['categories' => \App\Models\Category::all()->sortBy('position'), 'types' => \App\Models\Type::all()->sortBy('position'), 'resolutions' => \App\Models\Resolution::all()->sortBy('position'), 'torrent' => $torrent]);
    }

    /**
     * Edit A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \ErrorException
     * @throws \HttpInvalidParamException
     */
    public function edit(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        \abort_unless($user->group->is_modo || $user->id == $torrent->user_id, 403);
        $torrent->name = $request->input('name');
        $torrent->slug = \Illuminate\Support\Str::slug($torrent->name);
        $torrent->description = $request->input('description');
        $torrent->category_id = $request->input('category_id');
        $torrent->imdb = $request->input('imdb');
        $torrent->tvdb = $request->input('tvdb');
        $torrent->tmdb = $request->input('tmdb');
        $torrent->mal = $request->input('mal');
        $torrent->igdb = $request->input('igdb');
        $torrent->type_id = $request->input('type_id');
        $torrent->resolution_id = $request->input('resolution_id');
        $torrent->mediainfo = $request->input('mediainfo');
        $torrent->anon = $request->input('anonymous');
        $torrent->stream = $request->input('stream');
        $torrent->sd = $request->input('sd');
        $torrent->internal = $request->input('internal');
        $v = \validator($torrent->toArray(), ['name' => 'required', 'slug' => 'required', 'description' => 'required', 'category_id' => 'required|exists:categories,id', 'type_id' => 'required|exists:types,id', 'resolution_id' => 'nullable|exists:resolutions,id', 'imdb' => 'required|numeric', 'tvdb' => 'required|numeric', 'tmdb' => 'required|numeric', 'mal' => 'required|numeric', 'igdb' => 'required|numeric', 'anon' => 'required', 'stream' => 'required', 'sd' => 'required']);
        if ($v->fails()) {
            return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors($v->errors());
        }
        $torrent->save();
        $tmdbScraper = new \App\Services\Tmdb\TMDBScraper();
        if ($torrent->category->tv_meta) {
            if ($torrent->tmdb || $torrent->tmdb != 0) {
                $tmdbScraper->tv($torrent->tmdb);
            }
        }
        if ($torrent->category->movie_meta) {
            if ($torrent->tmdb || $torrent->tmdb != 0) {
                $tmdbScraper->movie($torrent->tmdb);
            }
        }

        return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('Successfully Edited!');
    }

    /**
     * Delete A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteTorrent(\Illuminate\Http\Request $request)
    {
        $v = \validator($request->all(), ['id' => 'required|exists:torrents', 'slug' => 'required|exists:torrents', 'message' => 'required|alpha_dash|min:1']);
        if ($v) {
            $user = $request->user();
            $id = $request->id;
            $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
            if ($user->group->is_modo || ($user->id == $torrent->user_id && \Carbon\Carbon::now()->lt($torrent->created_at->addDay()))) {
                $users = \App\Models\History::where('info_hash', '=', $torrent->info_hash)->get();
                foreach ($users as $pm) {
                    $privateMessage = new \App\Models\PrivateMessage();
                    $privateMessage->sender_id = 1;
                    $privateMessage->receiver_id = $pm->user_id;
                    $privateMessage->subject = \sprintf('Torrent Deleted! - %s', $torrent->name);
                    $privateMessage->message = \sprintf('[b]Attention:[/b] Torrent %s has been removed from our site. Our system shows that you were either the uploader, a seeder or a leecher on said torrent. We just wanted to let you know you can safely remove it from your client.
                                        [b]Removal Reason:[/b] %s
                                        [color=red][b]THIS IS AN AUTOMATED SYSTEM MESSAGE, PLEASE DO NOT REPLY![/b][/color]', $torrent->name, $request->message);
                    $privateMessage->save();
                }
                // Reset Requests
                $torrentRequest = \App\Models\TorrentRequest::where('filled_hash', '=', $torrent->info_hash)->get();
                foreach ($torrentRequest as $req) {
                    if ($req) {
                        $req->filled_by = null;
                        $req->filled_when = null;
                        $req->filled_hash = null;
                        $req->approved_by = null;
                        $req->approved_when = null;
                        $req->save();
                    }
                }
                //Remove Torrent related info
                \cache()->forget(\sprintf('torrent:%s', $torrent->info_hash));
                \App\Models\Peer::where('torrent_id', '=', $id)->delete();
                \App\Models\History::where('info_hash', '=', $torrent->info_hash)->delete();
                \App\Models\Warning::where('torrent', '=', $id)->delete();
                \App\Models\TorrentFile::where('torrent_id', '=', $id)->delete();
                \App\Models\PlaylistTorrent::where('torrent_id', '=', $id)->delete();
                \App\Models\Subtitle::where('torrent_id', '=', $id)->delete();
                \App\Models\Graveyard::where('torrent_id', '=', $id)->delete();
                if ($torrent->featured == 1) {
                    \App\Models\FeaturedTorrent::where('torrent_id', '=', $id)->delete();
                }
                $torrent->delete();

                return \redirect()->route('torrents')->withSuccess('Torrent Has Been Deleted!');
            }
        } else {
            $errors = '';
            foreach ($v->errors()->all() as $error) {
                $errors .= $error."\n";
            }
            \Illuminate\Support\Facades\Log::notice(\sprintf('Deletion of torrent failed due to: %s', $errors));

            return \redirect()->route('home.index')->withErrors('Unable to delete Torrent');
        }
    }

    /**
     * Display Peers Of A Torrent.
     *
     * @param \App\Models\Torrent $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function peers(Torrent $id)
    {
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $peers = \App\Models\Peer::with(['user'])->where('torrent_id', '=', $id)->latest('seeder')->paginate(25);

        return \view('torrent.peers', ['torrent' => $torrent, 'peers' => $peers]);
    }

    /**
     * Display History Of A Torrent.
     *
     * @param \App\Models\Torrent $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function history(Torrent $id)
    {
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $history = \App\Models\History::with(['user'])->where('info_hash', '=', $torrent->info_hash)->latest()->paginate(25);

        return \view('torrent.history', ['torrent' => $torrent, 'history' => $history]);
    }

    /**
     * Torrent Upload Form.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $category_id
     * @param string                   $title
     * @param int                      $imdb
     * @param int                      $tmdb
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function uploadForm(\Illuminate\Http\Request $request, $categoryId = 0, $title = '', $imdb = 0, $tmdb = 0)
    {
        $user = $request->user();

        return \view('torrent.upload', ['categories' => \App\Models\Category::all()->sortBy('position'), 'types' => \App\Models\Type::all()->sortBy('position'), 'resolutions' => \App\Models\Resolution::all()->sortBy('position'), 'user' => $user, 'category_id' => $categoryId, 'title' => $title, 'imdb' => \str_replace('tt', '', $imdb), 'tmdb' => $tmdb]);
    }

    /**
     * Upload A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \ErrorException
     * @throws \HttpInvalidParamException
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function upload(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        // Find the right category
        $category = \App\Models\Category::withCount('torrents')->findOrFail($request->input('category_id'));
        // Preview The Upload
        $previewContent = null;
        if ($request->get('preview') == true) {
            $bbcode = new \App\Helpers\Bbcode();
            $previewContent = $bbcode->parse($request->input('description'), true);

            return \redirect()->route('upload_form', ['category_id' => $category->id])->withInput()->with(['previewContent' => $previewContent])->withWarning('Torrent Description Preview Loaded!');
        }
        $requestFile = $request->file('torrent');
        if ($request->hasFile('torrent') == false) {
            return \redirect()->route('upload_form', ['category_id' => $category->id])->withErrors('You Must Provide A Torrent File For Upload!')->withInput();
        }
        if ($requestFile->getError() != 0 && $requestFile->getClientOriginalExtension() !== 'torrent') {
            return \redirect()->route('upload_form', ['category_id' => $category->id])->withErrors('An Unknown Error Has Occurred!')->withInput();
        }
        // Deplace and decode the torrent temporarily
        $decodedTorrent = \App\Helpers\TorrentTools::normalizeTorrent($requestFile);
        $infohash = \App\Helpers\Bencode::get_infohash($decodedTorrent);
        $meta = \App\Helpers\Bencode::get_meta($decodedTorrent);
        $fileName = \uniqid('', true).'.torrent';
        // Generate a unique name
        \file_put_contents(\getcwd().'/files/torrents/'.$fileName, \App\Helpers\Bencode::bencode($decodedTorrent));
        // Create the torrent (DB)
        $torrent = new \App\Models\Torrent();
        $torrent->name = $request->input('name');
        $torrent->slug = \Illuminate\Support\Str::slug($torrent->name);
        $torrent->description = $request->input('description');
        $torrent->mediainfo = self::anonymizeMediainfo($request->input('mediainfo'));
        $torrent->info_hash = $infohash;
        $torrent->file_name = $fileName;
        $torrent->num_file = $meta['count'];
        $torrent->announce = $decodedTorrent['announce'];
        $torrent->size = $meta['size'];
        $torrent->nfo = $request->hasFile('nfo') ? \App\Helpers\TorrentTools::getNfo($request->file('nfo')) : '';
        $torrent->category_id = $category->id;
        $torrent->type_id = $request->input('type_id');
        $torrent->resolution_id = $request->input('resolution_id');
        $torrent->user_id = $user->id;
        $torrent->imdb = $request->input('imdb');
        $torrent->tvdb = $request->input('tvdb');
        $torrent->tmdb = $request->input('tmdb');
        $torrent->mal = $request->input('mal');
        $torrent->igdb = $request->input('igdb');
        $torrent->anon = $request->input('anonymous');
        $torrent->stream = $request->input('stream');
        $torrent->sd = $request->input('sd');
        $torrent->internal = $request->input('internal');
        $torrent->moderated_at = \Carbon\Carbon::now();
        $torrent->moderated_by = 1;
        //System ID
        $torrent->free = $user->group->is_modo || $user->group->is_internal ? $request->input('free') : 0;
        // Validation
        $v = \validator($torrent->toArray(), ['name' => 'required|unique:torrents', 'slug' => 'required', 'description' => 'required', 'info_hash' => 'required|unique:torrents', 'file_name' => 'required', 'num_file' => 'required|numeric', 'announce' => 'required', 'size' => 'required', 'category_id' => 'required|exists:categories,id', 'type_id' => 'required|exists:types,id', 'resolution_id' => 'nullable|exists:resolutions,id', 'user_id' => 'required|exists:users,id', 'imdb' => 'required|numeric', 'tvdb' => 'required|numeric', 'tmdb' => 'required|numeric', 'mal' => 'required|numeric', 'igdb' => 'required|numeric', 'anon' => 'required', 'stream' => 'required', 'sd' => 'required']);
        if ($v->fails()) {
            if (\file_exists(\getcwd().'/files/torrents/'.$fileName)) {
                \unlink(\getcwd().'/files/torrents/'.$fileName);
            }

            return \redirect()->route('upload_form', ['category_id' => $category->id])->withErrors($v->errors())->withInput();
        }
        // Save The Torrent
        $torrent->save();
        // Count and save the torrent number in this category
        $category->num_torrent = $category->torrents_count;
        $category->save();
        // Backup the files contained in the torrent
        $fileList = \App\Helpers\TorrentTools::getTorrentFiles($decodedTorrent);
        foreach ($fileList as $file) {
            $torrentFile = new \App\Models\TorrentFile();
            $torrentFile->name = $file['name'];
            $torrentFile->size = $file['size'];
            $torrentFile->torrent_id = $torrent->id;
            $torrentFile->save();
            unset($torrentFile);
        }
        $tmdbScraper = new \App\Services\Tmdb\TMDBScraper();
        if ($torrent->category->tv_meta) {
            if ($torrent->tmdb || $torrent->tmdb != 0) {
                $tmdbScraper->tv($torrent->tmdb);
            }
        }
        if ($torrent->category->movie_meta) {
            if ($torrent->tmdb || $torrent->tmdb != 0) {
                $tmdbScraper->movie($torrent->tmdb);
            }
        }
        // Torrent Keywords System
        $keywords = self::parseKeywords($request->input('keywords'));
        foreach ($keywords as $keyword) {
            $tag = new \App\Models\Keyword();
            $tag->name = $keyword;
            $tag->torrent_id = $torrent->id;
            $tag->save();
        }
        // check for trusted user and update torrent
        if ($user->group->is_trusted) {
            $appurl = \config('app.url');
            $user = $torrent->user;
            $username = $user->username;
            $anon = $torrent->anon;
            // Announce To Shoutbox
            if ($anon == 0) {
                $this->chatRepository->systemMessage(\sprintf('User [url=%s/users/', $appurl).$username.']'.$username.\sprintf('[/url] has uploaded [url=%s/torrents/', $appurl).$torrent->id.']'.$torrent->name.'[/url] grab it now! :slight_smile:');
            } else {
                $this->chatRepository->systemMessage(\sprintf('An anonymous user has uploaded [url=%s/torrents/', $appurl).$torrent->id.']'.$torrent->name.'[/url] grab it now! :slight_smile:');
            }
            \App\Helpers\TorrentHelper::approveHelper($torrent->id);
        }

        return \redirect()->route('download_check', ['id' => $torrent->id])->withSuccess('Your torrent file is ready to be downloaded and seeded!');
    }

    /**
     * Download Check.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function downloadCheck(\Illuminate\Http\Request $request, Torrent $id)
    {
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $user = $request->user();

        return \view('torrent.download_check', ['torrent' => $torrent, 'user' => $user]);
    }

    /**
     * Download A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     * @param null                     $rsskey
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(\Illuminate\Http\Request $request, Torrent $id, $rsskey = null)
    {
        $user = $request->user();
        if (! $user && $rsskey) {
            $user = \App\Models\User::where('rsskey', '=', $rsskey)->firstOrFail();
        }
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        // User's ratio is too low
        if ($user->getRatio() < \config('other.ratio')) {
            return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('Your Ratio Is To Low To Download!');
        }
        // User's download rights are revoked
        if ($user->can_download == 0 && $torrent->user_id != $user->id) {
            return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('Your Download Rights Have Been Revoked!');
        }
        // Torrent Status Is Rejected
        if ($torrent->isRejected()) {
            return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('This Torrent Has Been Rejected By Staff');
        }
        // Define the filename for the download
        $tmpFileName = \str_replace([' ', '/', '\\'], ['.', '-', '-'], '['.\config('torrent.source').']'.$torrent->name.'.torrent');
        // The torrent file exist ?
        if (! \file_exists(\getcwd().'/files/torrents/'.$torrent->file_name)) {
            return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('Torrent File Not Found! Please Report This Torrent!');
        }
        // Delete the last torrent tmp file
        if (\file_exists(\getcwd().'/files/tmp/'.$tmpFileName)) {
            \unlink(\getcwd().'/files/tmp/'.$tmpFileName);
        }
        // Get the content of the torrent
        $dict = \App\Helpers\Bencode::bdecode(\file_get_contents(\getcwd().'/files/torrents/'.$torrent->file_name));
        if ($request->user() || ($rsskey && $user)) {
            // Set the announce key and add the user passkey
            $dict['announce'] = \route('announce', ['passkey' => $user->passkey]);
            // Remove Other announce url
            unset($dict['announce-list']);
        } else {
            return \redirect()->route('login');
        }
        $fileToDownload = \App\Helpers\Bencode::bencode($dict);
        \file_put_contents(\getcwd().'/files/tmp/'.$tmpFileName, $fileToDownload);

        return \response()->download(\getcwd().'/files/tmp/'.$tmpFileName)->deleteFileAfterSend(true);
    }

    /**
     * Bump A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bumpTorrent(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        \abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $torrent->bumped_at = \Carbon\Carbon::now();
        $torrent->save();
        // Announce To Chat
        $torrentUrl = \href_torrent($torrent);
        $profileUrl = \href_profile($user);
        $this->chatRepository->systemMessage(\sprintf('Attention, [url=%s]%s[/url] has been bumped to the top by [url=%s]%s[/url]! It could use more seeds!', $torrentUrl, $torrent->name, $profileUrl, $user->username));
        // Announce To IRC
        if (\config('irc-bot.enabled') == true) {
            $appname = \config('app.name');
            $ircAnnounceBot = new \App\Bots\IRCAnnounceBot();
            $ircAnnounceBot->message(\config('irc-bot.channel'), '['.$appname.'] User '.$user->username.' has bumped '.$torrent->name.' , it could use more seeds!');
            $ircAnnounceBot->message(\config('irc-bot.channel'), '[Category: '.$torrent->category->name.'] [Type: '.$torrent->type->name.'] [Size:'.$torrent->getSize().']');
            $ircAnnounceBot->message(\config('irc-bot.channel'), \sprintf('[Link: %s]', $torrentUrl));
        }

        return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('Torrent Has Been Bumped To The Top Successfully!');
    }

    /**
     * Sticky A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sticky(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        \abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $torrent->sticky = $torrent->sticky == 0 ? '1' : '0';
        $torrent->save();

        return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('Torrent Sticky Status Has Been Adjusted!');
    }

    /**
     * 100% Freeleech A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function grantFL(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        \abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $torrentUrl = \href_torrent($torrent);
        if ($torrent->free == 0) {
            $torrent->free = '1';
            $this->chatRepository->systemMessage(\sprintf('Ladies and Gents, [url=%s]%s[/url] has been granted 100%% FreeLeech! Grab It While You Can! :fire:', $torrentUrl, $torrent->name));
        } else {
            $torrent->free = '0';
            $this->chatRepository->systemMessage(\sprintf('Ladies and Gents, [url=%s]%s[/url] has been revoked of its 100%% FreeLeech! :poop:', $torrentUrl, $torrent->name));
        }
        $torrent->save();

        return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('Torrent FL Has Been Adjusted!');
    }

    /**
     * Feature A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function grantFeatured(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        \abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        if ($torrent->featured == 0) {
            $torrent->free = '1';
            $torrent->doubleup = '1';
            $torrent->featured = '1';
            $torrent->save();
            $featured = new \App\Models\FeaturedTorrent();
            $featured->user_id = $user->id;
            $featured->torrent_id = $torrent->id;
            $featured->save();
            $torrentUrl = \href_torrent($torrent);
            $profileUrl = \href_profile($user);
            $this->chatRepository->systemMessage(\sprintf('Ladies and Gents, [url=%s]%s[/url] has been added to the Featured Torrents Slider by [url=%s]%s[/url]! Grab It While You Can! :fire:', $torrentUrl, $torrent->name, $profileUrl, $user->username));

            return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('Torrent Is Now Featured!');
        }

        return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('Torrent Is Already Featured!');
    }

    /**
     * Double Upload A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function grantDoubleUp(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        \abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $torrentUrl = \href_torrent($torrent);
        if ($torrent->doubleup == 0) {
            $torrent->doubleup = '1';
            $this->chatRepository->systemMessage(\sprintf('Ladies and Gents, [url=%s]%s[/url] has been granted Double Upload! Grab It While You Can! :fire:', $torrentUrl, $torrent->name));
        } else {
            $torrent->doubleup = '0';
            $this->chatRepository->systemMessage(\sprintf('Ladies and Gents, [url=%s]%s[/url] has been revoked of its Double Upload! :poop:', $torrentUrl, $torrent->name));
        }
        $torrent->save();

        return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('Torrent DoubleUpload Has Been Adjusted!');
    }

    /**
     * Reseed Request A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reseedTorrent(\Illuminate\Http\Request $request, Torrent $id)
    {
        $appurl = \config('app.url');
        $user = $request->user();
        $torrent = \App\Models\Torrent::findOrFail($id);
        $reseed = \App\Models\History::where('info_hash', '=', $torrent->info_hash)->where('active', '=', 0)->get();
        if ($torrent->seeders <= 2) {
            // Send Notification
            foreach ($reseed as $r) {
                \App\Models\User::find($r->user_id)->notify(new \App\Notifications\NewReseedRequest($torrent));
            }
            $torrentUrl = \href_torrent($torrent);
            $profileUrl = \href_profile($user);
            $this->chatRepository->systemMessage(\sprintf('Ladies and Gents, a reseed request was just placed on [url=%s]%s[/url] can you help out :question:', $torrentUrl, $torrent->name));

            return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('A notification has been sent to all users that downloaded this torrent along with original uploader!');
        }

        return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('This torrent doesnt meet the rules for a reseed request.');
    }

    /**
     * Use Freeleech Token On A Torrent.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Torrent      $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function freeleechToken(\Illuminate\Http\Request $request, Torrent $id)
    {
        $user = $request->user();
        $torrent = \App\Models\Torrent::withAnyStatus()->findOrFail($id);
        $activeToken = \App\Models\FreeleechToken::where('user_id', '=', $user->id)->where('torrent_id', '=', $torrent->id)->first();
        if ($user->fl_tokens >= 1 && ! $activeToken) {
            $freeleechToken = new \App\Models\FreeleechToken();
            $freeleechToken->user_id = $user->id;
            $freeleechToken->torrent_id = $torrent->id;
            $freeleechToken->save();
            $user->fl_tokens -= '1';
            $user->save();

            return \redirect()->route('torrent', ['id' => $torrent->id])->withSuccess('You Have Successfully Activated A Freeleech Token For This Torrent!');
        }

        return \redirect()->route('torrent', ['id' => $torrent->id])->withErrors('You Dont Have Enough Freeleech Tokens Or Already Have One Activated On This Torrent.');
    }
}
