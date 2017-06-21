<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScoreLog extends Model
{
    protected $table = "scorelogs";
    protected $fillable = ['user_id', 'reason', 'type', 'points'];


    public function user()
    {
        return $this->belongsTo('user');

    }

    public static function write($user_id, $data)
    {
        \App\ScoreLog::create([
            'user_id'   => $user_id,
            'points'    => $data['points'],
            'reason'    => $data['reason'],
            'type'      => $data['type']
        ]);

        $message = new \App\Message;
        $message->sender_id = null;
        $message->receiver_id = $user_id;
        if ($data['type'] == 'assign')
        {
            $message->subject = 'You received ' . $data['points'] .  ' points!'; // Implement message based on type here
            $message->content = 'You have received ' . $data['points'] . ' points for: ' . $data['reason'] . ' Congratulations!';
        } else
        {
            $message->subject = 'You lost ' . $data['points'] . '  points...';
            $message->content = 'You lost ' . $data['points'] . ' points for: ' . $data['reason'] . '.';
        }
        $message->save();


    }


}
