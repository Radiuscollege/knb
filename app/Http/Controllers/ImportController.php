<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UploadCsvRequest;
use Excel;

class ImportController extends Controller
{
    public $duplicates = 0;
    public $imports = 0;
    public $pointEntries = 0;

    /**
     * __construct
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
    }

    /**
     * stripAccents
     *
     * @param mixed $stripAccents
     * @return \Illuminate\Http\Response
     */
    private function stripAccents($stripAccents){
        return strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }

    /**
     * upload
     *
     * @param UploadCsvRequest $request
     * @return \Illuminate\Http\Response
     */
    public function upload(UploadCsvRequest $request)
    {
        $file = $request->file('csv');

        Excel::load($file, function($reader)  {

            $results = $reader->select(['klas', 'studentnr', 'naam', 'email'])->get();

            $house_id = 1;
            foreach ($results as $result)
            {
                $id = 'D'.$result->studentnr;

                if ($house_id == 5)
                {
                    $house_id = 1;
                }

                if (\App\User::where('id', '=', $id)->exists())
                {
                   $this->duplicates++;
                } else
                {
                    $user = new \App\User;
                    $user->id = $id;
                    $user->name     = $this->stripAccents($result->naam);
                    $user->email        = $result->email;
                    $user->type = 'student';
                    $user->points = 0;
                    $user->save();
                    \App\HouseRole::create(['user_id' => $user->id, 'house_id' => $house_id, 'role_level', '0']);
                    \App\Point::assign($user->id, \App\Point::BENEFACTOR_REGISTER_SYSTEM);
                    $user->addPoints(\App\Point::BENEFACTOR_REGISTER_SYSTEM, true);
                    $this->imports++;

                }
                $house_id++;
            }



        });
        return back()->with('success', 'Import successful. ' . $this->imports . ' added.' . $this->duplicates . ' duplicates were found.');
    }

    /**
     * singleRegistration
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function singleRegistration(Request $request)
    {

        $user = new \App\User;
        $user->name     = $request->name;
        $user->studentnr = $request->studentnr;
        $user->email        = $request->email;
        $user->password     = bcrypt( $user->studentnummer );
        $user->save();

        \App\HouseRole::create(['user_id' => $user->id, 'house_id' => $request->house_id, 'role_level', '0']);
        \App\Point::assign($user->id, \App\Point::BENEFACTOR_REGISTER_SYSTEM);
        $user->addPoints(\App\Point::BENEFACTOR_REGISTER_SYSTEM, true);
        return back()->with('succes', 'User ' . $user->name . ' succesfully registered to the amohub.');
    }

    public function bulkBadgesUpload(UploadCsvRequest $request)
    {
        $file = $request->file('csv');

        Excel::load($file, function($reader) {
            $results = $reader->select(['code', 'bt1', 'bt2', 'bt3', 'bt4', 'laat', 'aanwezig'])->get();
            foreach($results as $result)
            {
                $user = \App\User::find($result->code);
                $bt1 = intval($result->bt1);
                $bt2 = intval($result->bt2);
                $bt3 = intval($result->bt3);
                $bt4 = intval($result->bt4);
                $aanwezig = intval($result->aanwezig);
                $laat = intval($result->laat);
                if (count($user) > 0)
                {
                        // check if any of the grades are above 90
                    if ($bt1 >= 90 || $bt2 >= 90 || $bt3 >= 90 || $bt4 >= 90)
                    {
                        // check if not already has overachiever badge
                            // assign overachiever badge
                        if ( !$user->hasBadge(11) )
                        {
                            \App\Badge::assign($user->id, 11);
                        }
                    }

                    if ($aanwezig == 100)
                    {
                        // check if not already has Always on Time badge
                            // assign always on time badge
                        if (!$user->hasBadge(12))
                        {
                            \App\Badge::assign($user->id, 12);
                        }

                    }

                    if ($laat == 0)
                    {
                        if (!$user->hasBadge(13))
                        {
                            \App\Badge::assign($user->id, 13);
                        }
                    }
                }

            }
        });

        return back()->with('success', "Succesfully gifted badges.");

    }

    public function bulkPointsUpload(UploadCsvRequest $request)
    {
        $file = $request->file('csv');


        $revert = false;
        $word = 'awarded';
        if ($request->revert == 'on')
        {
            $revert = true;
            $word = 'deducted';
        }
        Excel::load($file, function($reader) use($revert, $word) {
            $results = $reader->select(['code', 'pgo', 'vht1', 'vht2', 'keuze', 'project', 'bt1', 'bt2', 'aanwezig'])->get();
            foreach($results as $result)
            {

                $user = \App\User::find($result->code);
                if (count($user) > 0)
                {
                    $this->imports++;
                    $pgo = intval($result->pgo);
                    $vht1 = strtolower($result->vht1);
                    $vht2 = strtolower($result->vht2);
                    $keuze = strtolower($result->keuze);
                    $project = strtolower($result->project);
                    $bt1 = intval($result->bt1);
                    $bt2 = intval($result->bt2);
                    $aanwezig = intval($result->aanwezig);
                    $res = [
                        [
                            "pgo" => $pgo,
                            "points" => $this->calculatePointsFromAttitude($pgo),
                            "message" => "Points $word for PGO this period."
                        ],
                        [
                            "vht1"  => $vht1,
                            "points" => $this->calculatePointsFromAttitude($vht1),
                            "message" => "Points $word for VHT1 this period."
                        ],
                        [
                            "vht2"  => $vht2,
                            "points"    => $this->calculatePointsFromAttitude($vht2),
                            "message" => "Points $word for VHT2 this period."
                        ],
                        [
                            "keuze" => $keuze,
                            "points"    => $this->calculatePointsFromAttitude($keuze),
                            "message"   => "Points $word for 'keuzedelen' this period."
                        ],
                        [
                            "project" => $project,
                            "points"    => $this->calculatePointsFromAttitude($project),
                            "message"   => "Points $word for 'project' this period."
                        ],
                        [
                            "bt1" => $bt1,
                            "points"  => $this->calculatePointsFromGrade($bt1),
                            "message" => "Points $word for 'bekwaamheidstoets 1' this period."
                        ],
                        [
                            "bt2" => $bt2,
                            "points"  => $this->calculatePointsFromGrade($bt2),
                            "message" => "Points $word for 'bekwaamheidstoets 2' this period."
                        ],
                        [
                            "aanwezig" => $aanwezig,
                            "points"    => $this->calculatePointsFromAttendance($aanwezig),
                            "message" => "Points $word for 'aanwezigheid' this period."
                        ]
                    ];

                    foreach($res as $r)
                    {
                        \App\Http\Controllers\PointsController::allocateFromBulk($user->id, $r, $revert);
                        $this->pointEntries++;
                    }

                }
            }
        });

        if (!$revert)
        {
            return back()->with('success', "$this->pointEntries point entries are done, $this->imports students are updated...");
        } else
        {
            return back()->with('success', "reverting of $this->pointEntries point entries are done, rolled back the points allocation of $this->imports students");
        }

    }

    public function calculatePointsFromGrade($grade)
    {

            $points = 0;
            switch($grade) {
                case $grade <= 55:
                    $points = 15;
                    break;
                case $grade <= 60:
                    $points = 20;
                    break;
                case $grade <= 65:
                    $points = 25;
                    break;
                case $grade <= 70:
                    $points = 30;
                    break;
                case $grade <= 75:
                    $points = 35;
                    break;
                case $grade <= 80:
                    $points = 40;
                    break;
                case $grade <= 85:
                    $points = 45;
                    break;
                case $grade <= 90:
                    $points = 50;
                    break;
                case $grade <= 95:
                    $points = 55;
                    break;
                case $grade < 100:
                    $points = 60;
                    break;
                case $grade == 100:
                    $points = 75;
                    break;
                default:
                    return 0;
            }
            return $points;
    }

    public function calculatePointsFromAttitude($rating)
    {
        $points = 0;
        switch($rating)
        {
            case $rating >= 55 && $rating < 75:
                $points = 25;
                break;
            case $rating >= 75 && $rating <= 100:
                $points = 50;
                break;
            case strtolower($rating) == 'v':
                $points = 25;
                break;
            case strtolower($rating) == 'g':
                $points = 50;
                break;
            default:
                return 0;
        }
        return $points;
    }

    public function calculatePointsFromAttendance($rating)
    {
        $points = 0;
        switch($rating)
        {
            case $rating >= 90 && $rating < 100:
                $points = 25;
                break;
            case $rating == 100:
                $points = 50;
                break;
            default:
                return 0;
                break;
        }
        return $points;
    }



}
