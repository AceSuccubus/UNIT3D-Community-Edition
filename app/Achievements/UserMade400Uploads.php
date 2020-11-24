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

namespace App\Achievements;

use Assada\Achievements\Achievement;

class UserMade400Uploads extends Achievement
{
    /*
     * The achievement name
     */
    public string $name = '400Uploads';

    /*
     * A small description for the achievement
     */
    public string $description = 'You have made 400 torrent uploads!';

    /*
     * The amount of "points" this user need to obtain in order to complete this achievement
     */
    public int $points = 400;
}
