<?php
if(!function_exists('get_user')){
    function get_user($request,$id=false){
        $url = $request->headers->get('referer');
        $user = null;
        if(preg_match('/user\/fastlogin\/(\d+)/i',$url,$m)){
            $id = $m[1];
        }
        if($id!==false){
            $user = \App\User::find($id);
        }
        else if($request->has('user_id')){
            $user = \App\User::find($request->user_id);
        }
        else $user = $request->user();
        return $user;
    }

}

?>
