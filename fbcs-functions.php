<?php

// function to get comment counts
function fbcsf_syncCommentCounts($post_ID)
{
    global $wpdb;
    global $post;
    $postCommentCount = $post->comment_count;
    $postCommentCount = @number_format($postCommentCount);
    $url = get_permalink($post_ID);
    $filecontent = file_get_contents('https://graph.facebook.com/?ids=' . $url);
    $json = json_decode($filecontent);
    $fbcsf_count = $json->$url->{'comments'};
    if ($fbcsf_count == 0 || !isset($fbcsf_count)) {
        $fbcsf_count = 0;
    }
    if ($fbcsf_count != $postCommentCount) {

        $table = $wpdb->prefix . 'posts';
        $data = array('comment_count' => $fbcsf_count);
        $where = array('ID' => $post_ID);
        $format = array('%d');
        $where_format = array('%d');
        $wpdb->update($table, $data, $where, $format, $where_format);
    }
}

// function to import comments
// Note to myself: In the next version, I should add the function checking if any comment is removed and ajax call failed.
function fbcsf_impComments($post_ID)
{
    global $wpdb;
    $url = get_permalink($post_ID);
    $filecontent = file_get_contents('https://graph.facebook.com/comments?id=' . $url);
    $json = json_decode($filecontent);
    $fbcsf_commentArray = $json->{'data'};
    $arrayCount = count($fbcsf_commentArray);
    for ($a = 0; $a < $arrayCount; $a++) {
        $fbcsf_commentID = $json->{'data'}[$a]->{'id'};
        $commentIDConverter = explode('_', $fbcsf_commentID);
        $fbcsf_commentID = substr($commentIDConverter[0], 0, 3) . substr($commentIDConverter[1], -16);
        $fbcsf_comment = $json->{'data'}[$a]->{'message'};
        $fbcsf_authorName = $json->{'data'}[$a]->{'from'}->{'name'};
        $fbcsf_authorID = $json->{'data'}[$a]->{'from'}->{'id'};
        $fbcsf_authorURL = 'www.facebook.com/' . $fbcsf_authorID;
        $fbcsf_time = $json->{'data'}[$a]->{'created_time'};
        $dateConverter = explode('T', $fbcsf_time);
        $timeConverter = explode('+', $dateConverter[1]);
        $fbcsf_time = $dateConverter[0] . ' ' . $timeConverter[0];
        $table = $wpdb->prefix . 'comments';
        $data = array(
            'comment_ID' => $fbcsf_commentID,
            'comment_content' => $fbcsf_comment,
            'comment_author' => $fbcsf_authorName,
            'comment_author_url' => $fbcsf_authorURL,
            'comment_date' => $fbcsf_time,
            'comment_post_ID' => $post_ID,
            'comment_approved' => '1'
        );
        $format = array(
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d'
        );
        $wpdb->insert($table, $data, $format);
    };
}

function fbcsf_postcount()
{
    global $wpdb;
    $posts = $wpdb->prefix . 'posts';
    $postsID = $wpdb->get_results(" SELECT ID FROM  $posts WHERE post_type =  'post' ", ARRAY_N);
    $postcount = count($postsID);
    echo $postcount;
}


function fbcsf_runfbcs()
{
    global $wpdb;
    $posts = $wpdb->prefix . 'posts';
    $postsID = $wpdb->get_results(" SELECT ID FROM  $posts WHERE post_type =  'post' ", ARRAY_N);
    $postcount = count($postsID);
    $start = $_POST['data'];
    $stop = $start + 3;

    if ($stop < $postcount) {
        for ($i = $start; $i <= $stop; $i++) {
            fbcsf_syncCommentCounts($postsID[$i][ID]);
            fbcsf_impComments($postsID[$i][ID]);
        }
    };
    echo $stop;

    wp_die();
}


function fbcsf_ajaxAComments($url, $postID) {
    $filecontent = file_get_contents('https://graph.facebook.com/comments?id=' . $url);
    $json = json_decode($filecontent);
    $fbcsf_commentArray = $json->{'data'};
    $arrayCount = count($fbcsf_commentArray);
    for ($a = 0; $a < $arrayCount; $a++) {
        $fbcsf_commentID = $json->{'data'}[$a]->{'id'};
        $commentIDConverter = explode('_', $fbcsf_commentID);
        $fbcsf_commentID = substr($commentIDConverter[0], 0, 3) . substr($commentIDConverter[1], -16);
        $fbcsf_comment = $json->{'data'}[$a]->{'message'};
        $fbcsf_authorName = $json->{'data'}[$a]->{'from'}->{'name'};
        $fbcsf_authorID = $json->{'data'}[$a]->{'from'}->{'id'};
        $fbcsf_authorURL = 'https://www.facebook.com/' . $fbcsf_authorID;
        $fbcsf_time = $json->{'data'}[$a]->{'created_time'};
        $dateConverter = explode('T', $fbcsf_time);
        $timeConverter = explode('+', $dateConverter[1]);
        $fbcsf_time = $dateConverter[0] . ' ' . $timeConverter[0];
        global $wpdb;
        $table = $wpdb->prefix . 'comments';
        $data = array(
            'comment_ID' => $fbcsf_commentID,
            'comment_content' => $fbcsf_comment,
            'comment_author' => $fbcsf_authorName,
            'comment_author_url' => $fbcsf_authorURL,
            'comment_date' => $fbcsf_time,
            'comment_post_ID' => $postID,
            'comment_approved' => '1'
        );
        $format = array(
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d'
        );

        $wpdb->insert($table, $data, $format);
    };
}

function fbcsf_ajaxRComments($fbcsf_commentID)
{
    wp_delete_comment($fbcsf_commentID, true);
}

function fbcsf_ajaxsyncCommentCounts($url, $postID)
{
    global $wpdb;
    $filecontent = file_get_contents('https://graph.facebook.com/?ids=' . $url);
    $json = json_decode($filecontent);


    $fbcsf_count = $json->$url->{'comments'};
    $table = $wpdb->prefix . 'posts';
    $data = array('comment_count' => $fbcsf_count);
    $where = array('ID' => $postID);
    $format = array('%d');
    $where_format = array('%d');
    $wpdb->update($table, $data, $where, $format, $where_format);
}

function fbcsf_runajaxCA()
{
    if (isset($_POST['myData'])) {
        $obj = $_POST['myData'];
        $url = $obj["href"];
        $postID = url_to_postid($obj["href"]);

        fbcsf_ajaxsyncCommentCounts($url, $postID);
        fbcsf_ajaxAComments($url, $postID);
    }
    wp_die();
}

function fbcsf_runajaxCR()
{
    if (isset($_POST['myData'])) {
        $obj = $_POST['myData'];
        $url = $obj['href'];
        $postID = url_to_postid($obj["href"]);
        $fbcsf_commentID = $obj['commentID'];
        $commentIDConverter = explode('_', $fbcsf_commentID);
        $fbcsf_commentID = substr($commentIDConverter[0], 0, 3) . substr($commentIDConverter[1], -16);

        fbcsf_ajaxsyncCommentCounts($url, $postID);
        fbcsf_ajaxRComments($fbcsf_commentID);
    }
    wp_die();
}
