<?php

declare(strict_types=1);

namespace App\Achievements;

use Assada\Achievements\Achievement;

class UserUploaded600Subtitles extends Achievement
{
    /*
     * The achievement name
     */
    public string $name = 'UserUploaded600Subtitles';

    /*
     * A small description for the achievement
     */
    public string $description = 'You have made 600 subtitle uploads!';

    /*
    * The amount of "points" this user need to obtain in order to complete this achievement
    */
    public int $points = 600;
}
