<?php

/**
 * Class DataController
 */
class DataController extends BaseController {

    

    function frontend() {
        
        if (Helper::isUser()) {
            $this->stream();
            return;
        }
        elseif(isset($_COOKIE['auth']))
        {
            
            //try reauth 
            $user = new User;
            $res=$user->find(array("auth_cookie"  => $_COOKIE['auth']));
            
            if(count($res)>0)
            {
                $_SESSION['login']=$res[0]->id;
                $_SESSION['user_settings']=$res[0]->settings;
                $this->stream();
                return;    
            }
            
        }
        
        $this->assign("title", "Das merken die nie");
        $this->assign("scope", "frontpage register");
        $this->render("main.php");
    }

    /**
     * this function loads the inital data for /random
     * it gets the max id from db, so the client can do 
     * some min/max calulation.
     */
    function random(){
        
        $content= new Content();
        //get max id for rand min/max
        $res=$content->getNext(Content::maxid, 1, false, false, false, false, false);
        $this->assign("random", $res[0]->id);
        
        $this->assign("title", "Das merken die nie");
        $this->assign("scope", "random");
        $this->render("stream.php");
    }
    
    /**
     * this method handles all requests arround stream data
     * and returns data as json formted string
     * 
     */
    function content() {
        $data = new Content;

        $id = (isset($_REQUEST['id']) && $_REQUEST['id'] != 0 ? (int) $_REQUEST['id'] : Content::maxid);
        $show = (isset($_REQUEST['show']) && $_REQUEST['show'] < 100 ? $_REQUEST['show'] : 10);
        $hash= (isset($_REQUEST['hash']) && $_REQUEST['hash'] != "" ? $_REQUEST['hash'] : false);
        $user= (isset($_REQUEST['user']) && $_REQUEST['user'] != "" ? $_REQUEST['user'] : false);
        
        //check nsfw content
        $settings=  Helper::getUserSettings();
        //while user is not logged in
        if($settings==false)
        {
            $settings=new stdClass();
            $settings->show_nsfw="false";
        }

        $data = $data->getNext($id, $show, $hash, $user, false, false, $settings->show_nsfw);
        header('Content-Type: application/json');
        $i = 0;

        foreach ($data as $res) {
            $std[$i] = new stdClass();
            $std[$i]->stream = new stdClass();
            $std[$i]->stream->type = "generic";

            if (isset($res->media) && $res->media != 'null') {
                $std[$i]->stream = json_decode($res->media);
                if (isset($std[$i]->stream->type)) {
                    switch ($std[$i]->stream->type) {
                        case 'img':
                            $std[$i]->stream->url = Config::get('upload_address') . $std[$i]->stream->url;
                            break;
                        case 'upload':
                            foreach ($std[$i]->stream->files as $fileKey => $file) {
                                $std[$i]->stream->files[$fileKey]->src = Config::get('upload_address').$std[$i]->stream->files[$fileKey]->src;
                            }
                            break;
                        default:
                            break;
                    }
                }
            }

            $std[$i]->stream->date = (int)$res->date;
            $std[$i]->stream->text = $res->data;
            $std[$i]->stream->id = (int)$res->id;

            if (isset($res->settings))
            {
                $std[$i]->author = json_decode($res->settings);
                $std[$i]->author->name = $res->name;
                $std[$i]->author->id = (int)$res->user_id;
                if (isset($std[$i]->author->profile_picture) && $std[$i]->author->profile_picture != 'null') {
                    $std[$i]->author->profile_picture = Config::get('upload_address') . $std[$i]->author->profile_picture;
                }
            }
            $i++;
        }

        if (isset($std))
            echo json_encode($std);
        else {
            echo json_encode(array());
        }
    }

    
    /**
     * load inital data for public stream 
     * 
     * @param type $request
     */
    function stream($request=false) {
        
        $data = new Content;
        $this->assign("show_share", true);
        $this->assign("title", "Public Stream");
        $this->render("stream.php");
    }

    /**
     * loads inital data for /<username>
     * @param type $request
     */
    function get_user($request){
        $this->assign("user", $request['user']);
        $this->assign("title", "Stream from ".str_replace(".", " ", $request['user'] ));
        $this->addHeader('<meta property="og:url" content="'.Config::get("address").''.$request['user'].'"/>');
        $this->addHeader('<meta property="og:title" content="Stream from '.str_replace(".", " ", $request['user'] ).'"/>');
        $this->addHeader('<meta property="og:type" content="website" />');
        $this->render("stream.php");
    }
    
    
    /**
     * load inital data for /hash/
     * and saves hashtag popularity
     * 
     * @param type $request
     */
    function get_hash($request){
        $hashdb= new Hashtags;
        $res=$hashdb->find(array("hashtag"=>$request['hash']));
        if(count($res)>0)
        {
            $res[0]->pop+=1;
            $res[0]->save();
        }else{
            $hashdb->hashtag= $request['hash'];
            $hashdb->pop=1;
            $hashdb->save();
        }
        
        $this->addHeader('<meta property="og:url" content="'.Config::get("address").'hash/'.$request['hash'].'"/>');
        $this->addHeader('<meta property="og:title" content="All about #'.$request['hash'].'"/>');
        $this->addHeader('<meta property="og:type" content="website" />');
        
        $this->assign("show_share", false);
        $this->assign("hash", $request['hash']);
        $this->assign("title", "All about ".$request['hash'] );
        $this->render("stream.php");
    }
    
    /**
     * loads inital data for permalink
     * like meta information
     * 
     * @param type $request
     */
    function get_permalink($request){
        $data = new Content;
        $res=$data->get($request['id']);
        
        if(strpos($res->data, "<code")!==false)
        {
            $res->data="";  
        }
        else
        {
            $res->data=  strip_tags($res->data);
        }

        $this->assign("title", $res->data);
        $media=json_decode($res->media);
        $this->addHeader('<link rel="canonical" href="'.Config::get("address").'permalink/'.$request['id'].'">');
        $this->addHeader('<meta property="og:url" content="'.Config::get("address").'permalink/'.$request['id'].'"/>');
        $this->addHeader('<meta property="og:title" content="'.$res->data.'"/>');
        $this->addHeader('<meta property="og:type" content="website" />');
        if(isset($media->img[0]))
            $this->addHeader('<meta property="og:image" content="'.Config::get("upload_address").$media->img[0].'"/>');
        if(isset($media->type) && $media->type=="img")
            $this->addHeader('<meta property="og:image" content="'.Config::get("upload_address").$media->url.'"/>');

        $this->assign("show_share", false);
        $this->assign("permalink", $request['id']);
        $this->render("stream.php");
    }
    
    /**
     * function handles new content
     * 
     * @return boolean
     */
    function post_content(){
        
        if (isset($_POST) && !empty($_POST)) 
        {      
            //spam bot prevention
            if(isset($_POST['mail']) && !empty($_POST['mail']))
                return false;
            
            $content = new Content();
            $content->data = $_POST['content'];
            $pattern="/(^|\s)#(\w*[a-zA-Z0-9öäü_-]+\w*)/";
            preg_match_all($pattern, $content->data, $hashtags);
            if(count($hashtags[0])>0)
            {
                $hashdb= new Hashtags;
                foreach($hashtags[0] as $hashtag)
                {
                    $hashdb->hashtag=trim(str_replace("#", "", $hashtag));
                    $hashdb->save();
                }
            }
            $metadata = json_decode($_POST['metadata']);
            if ($metadata->type == "img") {
                $metadata->url = $this->download($metadata->url);
            }
            
            if($metadata->type == "video")
            {
                if($metadata->dl==true)
                {
                    $filename = $this->download($metadata->url);
                    $metadata->url= Config::get("upload_address").$filename;
                    $metadata->html=$this->replaceVideo($metadata->url);
                    
                }
                
            }

            if (isset($_FILES) && !empty($_FILES['img']['name'][0]) && is_array($_FILES)) {
                $i=0;
                foreach ($_FILES['img']['tmp_name'] as $i => $file) {
                    $uniq = uniqid("", true) . "_" . $_FILES['img']['name'][$i];
                    $upload_path = Config::get("dir") . Config::get("upload_path");
                    move_uploaded_file($file, $upload_path . $uniq);
                    
                    $metadata->type = "upload";
                    
                    $mime = mime_content_type($upload_path . $uniq);

                    $metadata->files[$i]->src = $uniq;
                    $metadata->files[$i]->name = $_FILES['img']['name'][$i];
                    $metadata->files[$i]->type = $mime;
               
                    $i++;
                }
            }
            $content->media = json_encode($metadata);
            if(Helper::isUser())
                $content->user_id = $_SESSION['login'];
            else
                $content->user_id = 1;
            
            if(isset($_POST['api_key']))
            {
                $user=new User;
                $user=$user->getUserbyAPIKey($_POST['api_key']);
                
                $content->user_id= $user[0]->id;
            }
            $content->date=date("U");
            
            $content->save();
            $this->redirect("/public/stream/");
        }
    }

    
    function delete($request){
        $content = new Content();
        $res=$content->find(array("user_id" => $_SESSION['login'], "id" =>$request['id'] ));
        header('Content-Type: application/json');
        if(count($res)>0)
        {
            $content->delete($request['id']);
            echo json_encode(array("status" => "deleted"));
        }
        
    }
    function update($request){
        $content = new Content();
        $res=$content->find(array("user_id" => $_SESSION['login'], "id" =>$request['id'] ));
        header('Content-Type: application/json');
        if(count($res)>0)
        {
            parse_str(file_get_contents("php://input"),$put_vars);
            $res[0]->data=$put_vars['content'];
            $res[0]->save();
            
            $pattern="/(^|\s)#(\w*[a-zA-Z0-9öäü_-]+\w*)/";
            preg_match_all($pattern, $put_vars['content'], $hashtags);
            if(count($hashtags[0])>0)
            {
                $hashdb= new Hashtags;
                foreach($hashtags[0] as $hashtag)
                {
                    $hashdb->hashtag=trim(str_replace("#", "", $hashtag));
                    $hashdb->save();
                }
            }
            
            
            echo json_encode(array("status" => "done"));
        }
        
    }

    /**
     * download content from given $url
     * 
     * @param string $url
     * @return string $filename
     */
    function download($url) {

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_USERAGENT, 'www.dasmerkendienie.com');

        $rawdata = curl_exec($ch);
        curl_close($ch);

        $path_parts = pathinfo($url);
        $filename = hash("sha512", $url);
        if(isset($path_parts['extension']))
            $filename.=".".$path_parts['extension'];
        
        
        $fullpath = Config::get("dir") . Config::get("upload_path") . $filename;
        $fp = fopen($fullpath, 'x');
        fwrite($fp, $rawdata);
        return $filename;
    }

    
    /**
     * returns infomations about given url
     * 
     * @todo splitt image, www, video handling into smaller functions
     * @todo better fallback for og tag parsing
     * @return void
     */
    function metadata() {
        $url = $_GET['url'];

        header('Content-Type: application/json; charset=utf-8');
        //check for image
        $path_parts = pathinfo($url);
        if (isset($path_parts['extension'])) {
            $extensions = array("svg", "png", "jpg", "gif", "jpeg");
            if (in_array(strtolower($path_parts['extension']), $extensions)) {
                $data = array("type" => "img");
                echo json_encode($data);
                return;
            }
        }
        //check for video
        $extensions = array("webm", "mpeg", "mp4");
        if ((isset($path_parts['extension']) &&
                in_array(strtolower($path_parts['extension']), $extensions)) ||
                strpos($url, "youtube") ||
                strpos($url, "youtu.be") ||
                strpos($url, "vimeo") ||
                strpos($url, "redtube")
        ) {
            if (isset($path_parts['extension']) && in_array(strtolower($path_parts['extension']), $extensions))
                $downloadable = true;
            else
                $downloadable = false;

            $data = array(
                "type" => "video",
                "html" => DataController::replaceVideo($url),
                "dl" => $downloadable);
            echo json_encode($data);
            return;
        }

        //check for og tag
        $data = array("type" => "www");
        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);

        $dom = new DOMDocument;
        @$dom->loadHTML($result);
        foreach ($dom->getElementsByTagName('meta') as $tag) {
            if ($tag->getAttribute('property') === 'og:image') {
                $data['og_img'] = $tag->getAttribute('content');
            }
            if ($tag->getAttribute('property') === 'og:title') {
                $data['og_title'] = $tag->getAttribute('content');
            }
            if ($tag->getAttribute('property') === 'og:description') {
                $data['og_description'] = $tag->getAttribute('content');
            }
            if($tag->getAttribute('name') == 'description')
            {
              $data['alt_description']= $tag->getAttribute('content');  
            } 
            
        }
        //fallback (while no opengraph tags exist, we use title tag, and meta description)
        if(!isset($data['og_title'])){
             $data['og_title'] =  $dom->getElementsByTagName('title')->item(0)->textContent;
        }
        if(!isset($data['og_description']) && isset($data['alt_description']) && !empty($data['alt_description'])){
             $data['og_description'] =  $data['alt_description'];
        }
        
        if(!isset($data['og_img']))
        {
            $base=$dom->getElementsByTagName('base');
            if($base->length>0)
                $base =  $dom->getElementsByTagName('base')->item(0)->attributes->getNamedItem("href")->value;
            else
                $base=$url;
            
            $imgSrc=$dom->getElementsByTagName('img')->item(0)->attributes->getNamedItem("src")->value;
            
            if(substr($imgSrc, 0, 4)=="http")
                $data['og_img'] = $imgSrc;
            else
                $data['og_img'] = $base.$imgSrc;
        }
        
        echo json_encode($data);
    }

    /**
     * function was needed to replace hashs and links
     * @todo can be removed
     * @param string $data
     * @return string
     */
    static function replaceData($data) {

        $data = DataController::replaceLinks($data);
        $data = DataController::replaceHash($data);
        return $data;
    }

    static function replaceLinks($txt) {
        return preg_replace('@((www|http://|https://)[^ ]+)@', '<a href="\1">\1</a>', $txt);
    }

    static function replaceHash($txt) {
        return preg_replace('/(^|\s)#(\w*[a-zA-Z0-9öäü_-]+\w*)/', '\1<a href="http://www.dasmerkendienie.com/hash/\2">#\2</a>', $txt);
    }

    /**
     * this function replaced video and image filers
     * now this taks is done on client side
     * @todo can be removed 
     * @param string $media
     * @param array $size
     * @return string
     */
    static function replaceMedia($media, $size = false) {

        if (!$size) {
            $width = 630;
            $height = 630;
        } else {
            $width = $size['width'];
            $height = $size['height'];
        }
        $data = DataController::replaceVideo($media, true, $size);

        $path_parts = pathinfo($media);

        $extensions = array("svg", "png", "jpg", "gif", "jpeg");
        if (in_array(strtolower($path_parts['extension']), $extensions)) {
            $data = "<img src=\"".Config::get("upload_address").$path_parts['basename'] . "\">";
        }

        return $data;
    }

    static function replaceVideo($input, $dimension = false) {

        if (is_array($dimension)) {
            $width = $dimension['width'];
            $height = $dimension['height'];
        } else {
            $width = 400;
            $height = 350;
        }

        $search = "/(.+(\?|&)v=([a-zA-Z0-9_-]+).*)|https\:\/\/youtu.be\/([a-zA-Z0-9_-]+).*/";

        $youtube = '<iframe id="ytplayer" type="text/html"  width="' . $width . '" height="' . $height . '" src="https://www.youtube.com/embed/$3$4" frameborder="0"> </iframe> ';
        $input = preg_replace($search, $youtube, $input);

        $search = '/((http|https)\:\/\/)?([w]{3}\.)?vimeo.com\/(\d+)+/i';

        $vimeo = '<iframe src="http://player.vimeo.com/video/$4?badge=0" width="' . $width . '" height="' . $height . '" frameborder="0" webkitAllowFullScreen="true" mozallowfullscreen="true" 
allowFullScreen="true"></iframe>';
        $input = preg_replace($search, $vimeo, $input);

        $search = '/((http|https)\:\/\/)?([w]{3}\.)?redtube.com\/(\d+)+/i';
        $redtube = '<iframe src="http://embed.redtube.com/?id=$4" width="' . $width . '" height="' . $height . '" frameborder="0" scrolling="no"></iframe>';
        $input = preg_replace($search, $redtube, $input);

        $path_parts = pathinfo($input);
        if (isset($path_parts['extension']) && ($path_parts['extension'] == "webm" || $path_parts['extension'] == "mpeg" || $path_parts['extension'] == "mp4")) {
            $input = '<video width="' . $width . '" height="' . $height . '" autoplay="autoplay" loop controls ><source src="' . $input . '" type="video/webm">Your browser does not support the video tag 
or webm</video>';
#       $input ='<video controls loop ><source src="'.$input.'" type="video/webm">Your browser does not support the video tag or webm</video>';
        }

        return $input;
    }

}
