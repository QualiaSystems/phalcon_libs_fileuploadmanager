<?php

/**
 * 
 * File Name       : UploadFileManager.php
 * Encoding        : UTF-8
 * Creation Date   : 2018-01-12
 * Author 			: Shino Yahatabara(shino@qualias.jp)
 * 
 * Copyright © 2018 Qualia Systems Inc. All rights reserved.
 * 
 * ファイルのアップロードの管理
 * GCなど　画像とそれ以外
 * 
 * テンポラリディレクトリの指定
 * GCの実行
 * GCの実行タイミング（確率）の設定
 * 
 * * */
class UploadFileManager extends \Phalcon\Mvc\User\Component {

    /**
     * Function Name : setImagefileStrage()
     * 
     * @param String $dir : 画像ファイルを保存する、ディレクトリのフルパス　
     * @param 
     * @return ファイル名のフルパス
     * 
     * Created     : 2018-01-12 Shino.Yahatabara
     * Modified    : 
     * Description : 保存するディレクトリーと、ファイル名を作成する。
     */
    public function getTempPath($dir = null) {
        //ディレクトリーが空の場合、保存するディレクトリーパスを取得
        if (empty($dir)) {
            $dir = sys_get_temp_dir();
        }
        //保存するファイルを作成 
        $path = tempnam($dir, '_G_');
        return $path;
    }

    /**
     * Function Name : setImagefileStrage()
     * 
     * @param Int $value 確率
     * @param 
     * @return true / false
     * 
     * Created     : 2018-01-12 Shino.Yahatabara
     * Modified    : 
     * Description : ガベージコレクション　[1 / $value]
     */
    public function GC($value = 1000) {
        $random = rand(0, $value);
        if ($random == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Function Name : setImagefileStrage()
     * 
     * @param String $dir : 画像ファイルが保存されている、ディレクトリのフルパス
     * @param Int $value 確率
     * @return true / false
     * 
     * Created     : 2018-01-12 Shino.Yahatabara
     * Modified    : 
     * Description : ファイルの削除  [ full GC ]
     */
    public function fileDelete($dir, $value = 1000) {
        if (!empty($dir)) {
            //ランダムで削除を行うか行わないかを判定
            if ($this->GC($value)) {
            //パスでディレクトリーを開く
            $dirHandle = opendir($dir);
            //ファイルを一つずつ取り出して削除する
            while (false !== ($fileName = readdir($dirHandle))) {
                var_dump($fileName);

                if (mb_substr($fileName, 0, 3) == "_G_") {
                    unlink($dir ."\\". $fileName);
                }
            }
            //ディレクトリーを閉じる
            closedir($dirHandle);
            return true;
            }
        }
        return false;
    }

    /**
     * Function Name : getResizeImage()
     * 
     * @param String $path : フルパス付ファイル名
     * @param Int    $size : 縦横の長さ（正方形） Default 200px
     * @return Image(Binary)
     * 
     * Created     : 2018-01-12 Shino.Yahatabara
     * Modified    : 
     * Description : ファイルを指定サイズで調整する
     */
    public static function getResizeImage($path, $size = 200) {
        $thumbW = $size; // リサイズしたい大きさを指定
        $thumbH = $size;

        // 加工前の画像の情報を取得
        list($w, $h, $type) = getimagesize($path);
        //元画像の縦横の大きさを比べてどちらかにあわせる
        // なおかつ縦横の差をコピー開始位置として使えるようセット
        if ($w > $h) {
            // サイズが小さい方に合わせて、切り抜く 
            $diff = ($w - $h) * 0.5;
            $diffW = $h;
            $diffH = $h;
            $diffY = 0;
            $diffX = $diff;
        } elseif ($w < $h) {
            $diff = ($h - $w) * 0.5;
            $diffW = $w;
            $diffH = $w;
            $diffY = $diff;
            $diffX = 0;
        } elseif ($w === $h) {
            $diffW = $w;
            $diffH = $h;
            $diffY = 0;
            $diffX = 0;
        }
        // 新しく描画する土台画像を作成
        $thumbnail = imagecreatetruecolor($w, $h);

        // 加工前のファイルをフォーマット別に読み出す（この他にも対応可能なフォーマット有り）
        switch ($type) {
            case IMAGETYPE_JPEG:
                $originalImage = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                // 土台画像に透過設定をする
                imagealphablending($thumbnail, false);
                // 土台画像に透過設定を可能にする
                imagesavealpha($thumbnail, true);
                $originalImage = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $originalImage = imagecreatefromgif($path);
                break;
            default:
                return false;
        }

        imagecopyresampled($thumbnail, $originalImage, 0, 0, $diffX, $diffY, $thumbW, $thumbH, $w, $h);
        //imagejpegなどは、変数で取得できず、アウトプットしてしまうため、バッファに記憶させている
        ob_start();
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, NULL, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, NULL, 0);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumbnail, NULL, 0);
                break;
        }
        $resize_image = ob_get_contents();
        ob_end_clean();
        //必要のない画像を開放させる
        imagedestroy($thumbnail);
        imagedestroy($originalImage);
        return $resize_image;
    }

    /**
     * Function Name : getImageType()
     * 
     * @param String $path : フルパス付ファイル名
     * @param 
     * @return 
     * 
     * Created     : 2018-01-12 Shino.Yahatabara
     * Modified    : 
     * Description : 画像のタイプを取得
     */
    public static function getImageType($path) {
        //画像ファイルデータを取得
        //error＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊
        $img_data = file_get_contents($path);
        // mimetype 拡張モジュール風に mime タイプを返します
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $img_data);
        finfo_close($finfo);
        //拡張子の配列（拡張子の種類を増やせば、画像以外のファイルでもOKです）
        $extension_array = array(
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png'
        );
        //MIMEタイプから拡張子を出力
        if ($img_extension = array_search($mime_type, $extension_array, true)) {
            //拡張子の出力
            return $img_extension;
        }
    }

    /**
     * Function Name : getImageSizeDistinctive()
     * 
     * @param String $path : フルパス付ファイル名
     * @param Int $within_w : サイズを判定　縦
     * @return Int $within_h : サイズを判定　横 
     * 
     * Created     : 2018-01-12 Shino.Yahatabara
     * Modified    : 
     * Description : 画像のサイズを判定する 
     */
    public static function getImageSizeDistinctive($path, $within_w, $within_h) {
        list($w, $h) = getimagesize($path);
        if ($w <= $within_w) {

            if ($h <= $within_h) {
                return true;
            }
        }
        return false;
    }

}
