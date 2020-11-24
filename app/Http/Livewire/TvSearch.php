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

namespace App\Http\Livewire;

use App\Models\Tv;
use Livewire\Component;
use Livewire\WithPagination;

class TvSearch extends Component
{
    use WithPagination;

    public string $searchTerm = '';

    protected array $queryString = ['searchTerm'];

    public function paginationView()
    {
        return 'vendor.pagination.livewire-pagination';
    }

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function render()
    {
        return \view('livewire.tv-search', [
            'shows' => Tv::with('networks', 'genres')
                ->withCount('torrents', 'seasons')
                ->where('name', 'LIKE', '%'.$this->searchTerm.'%')
                ->orderBy('name', 'asc')
                ->paginate(30),
        ]);
    }
}
