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

namespace App\Http\Controllers;

use App\Models\Album;

/**
 * @see \Tests\Feature\Http\Controllers\AlbumControllerTest
 */
class AlbumController extends \App\Http\Controllers\Controller
{
    /**
     * Display All Albums.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $albums = \App\Models\Album::withCount('images')->get();

        return \view('album.index')->with('albums', $albums);
    }

    /**
     * Show Album Create Form.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        return \view('album.create');
    }

    /**
     * Store A New Album.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $imdb = \Illuminate\Support\Str::startsWith($request->input('imdb'), 'tt') ? $request->input('imdb') : 'tt'.$request->input('imdb');
        $meta = \App\Models\Movie::where('imdb_id', '=', $imdb)->first();
        if ($meta === null || ! $meta) {
            return \redirect()->route('albums.create')->withErrors('Meta Data Not Found. Gallery System Is Being Refactored');
        }
        $album = new \App\Models\Album();
        $album->user_id = $request->user()->id;
        $album->name = $meta->title.' ('.$meta->release_date.')';
        $album->description = $request->input('description');
        $album->imdb = $request->input('imdb');
        $image = $request->file('cover_image');
        $filename = 'album-cover_'.\uniqid('', true).'.'.$image->getClientOriginalExtension();
        $path = \public_path('/files/img/'.$filename);
        \Intervention\Image\Facades\Image::make($image->getRealPath())->fit(400, 225)->encode('png', 100)->save($path);
        $album->cover_image = $filename;
        $v = \validator($album->toArray(), ['user_id' => 'required', 'name' => 'required', 'description' => 'required', 'imdb' => 'required', 'cover_image' => 'required']);
        if ($v->fails()) {
            return \redirect()->route('albums.create')->withInput()->withErrors($v->errors());
        }
        $album->save();

        return \redirect()->route('albums.show', ['id' => $album->id])->withSuccess('Your album has successfully published!');
    }

    /**
     * Show A Album.
     *
     * @param \App\Models\Album $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($id)
    {
        $album = \App\Models\Album::with('images')->find($id);
        $albums = \App\Models\Album::with('images')->get();

        return \view('album.show', ['album' => $album, 'albums' => $albums]);
    }

    /**
     * Delete A Album.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Album        $id
     *
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(\Illuminate\Http\Request $request, $id)
    {
        $user = $request->user();
        $album = \App\Models\Album::findOrFail($id);
        \abort_unless($user->group->is_modo || ($user->id === $album->user_id && \Carbon\Carbon::now()->lt($album->created_at->addDay())), 403);
        $album->delete();

        return \redirect()->route('albums.index')->withSuccess('Album has successfully been deleted');
    }
}
