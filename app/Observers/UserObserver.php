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

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     *
     * @param \App\Models\User $user
     *
     * @return void
     */
    public function created(User $user)
    {
        //\cache()->put(\sprintf('user:%s', $user->passkey), $user);
    }

    /**
     * Handle the User "updated" event.
     *
     * @param \App\Models\User $user
     *
     * @return void
     */
    public function updated(User $user)
    {
        //\cache()->put(\sprintf('user:%s', $user->passkey), $user);
    }

    /**
     * Handle the User "retrieved" event.
     *
     * @param \App\Models\User $user
     *
     * @return void
     */
    public function retrieved(User $user)
    {
        //\cache()->add(\sprintf('user:%s', $user->passkey), $user);
    }

    /**
     * Handle the User "deleted" event.
     *
     * @param \App\Models\User $user
     *
     * @return void
     */
    public function deleted(User $user)
    {
        //\cache()->forget(\sprintf('user:%s', $user->passkey));
    }

    /**
     * Handle the User "restored" event.
     *
     * @param \App\Models\User $user
     *
     * @return void
     */
    public function restored(User $user)
    {
        //\cache()->put(\sprintf('user:%s', $user->passkey), $user);
    }
}
