<?php
namespace TekBooth\Workers;
use Pubnub\Pubnub;
use TekBooth\Daemon\ClosureDaemon;
use TekBooth\Service\Darkroom\GithubDarkroom;
use TekBooth\Service\GoPro\Client as GoPro;

/**
 * Photographer actually takes the photos. Waiting for a message that includes a 'session' id, once the photo is taken,
 * the image filename (not the file itself) is sent to storage. The Developer is expected to actually process the image
 * and upload.
 *
 * Needs the storage service, the message service, and the camera service.
 */

class Photographer
{
    /**
     * @var GoPro
     */
    protected $gopro;

    /**
     * @var Pubnub
     */
    protected $pubnub;

    /**
     * @var GithubDarkroom
     */
    protected $darkroom;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var ClosureDaemon
     */
    protected $daemon;

    public function __construct(GoPro $gopro, Pubnub $pubnub, GithubDarkroom $githubDarkroom, $channel)
    {
        $this->gopro = $gopro;
        $this->pubnub = $pubnub;
        $this->darkroom = $githubDarkroom;
        $this->channel = $channel;
    }

    public function process($data)
    {
        $this->daemon->tick();

        //grab session id and number
        $data['message'] = array_merge([
            'count' => 1,
            'mode'  => 'photo',
            'delay' => 0
        ],$data['message']);

        $session = $data['message']['session'];
        $number  = $data['message']['number'];
        $mode    = $data['message']['mode'];
        $count   = $data['message']['count'];
        $delay   = $data['message']['delay'];

        if(!$session){
            error_log('no session');
            return $this->daemon->run;
        }

        error_log('message data: ' . json_encode($data['message']));

        if($delay){
            sleep($delay);
        }

        //take photo
        switch($mode){
            case 'photo':
            default:
                error_log('taking photo');
                $this->gopro->shutter();
                sleep(2);
                break;
        }

        $last = $this->gopro->getLastFile(GoPro::FILTER_PHOTO);
        error_log('got last file: ' . $last);

        error_log('adding photo to session: ' . $last);
        $this->darkroom->addPhoto($session, $last, $number);

        $this->daemon->tick();
        return $this->daemon->run;
    }

    public function __invoke(ClosureDaemon $daemon)
    {
        $this->daemon = $daemon;

        $camera = $this->gopro;
        $darkroom = $this->darkroom;


        error_log('subscribed to channel: ' . $this->channel);
        $this->pubnub->subscribe($this->channel, [$this, 'process']);

        return $this->daemon->run;
    }

    public function setup()
    {
        $this->gopro->setMode(GoPro::MODE_PHOTO);
        sleep(3);
        $this->gopro->setPhotoResolution(06);
        sleep(3);
    }
}