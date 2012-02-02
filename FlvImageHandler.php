<?php

/**#@+
 * An image handler which adds support for Flash video (.flv) and MP4 H.264/AAC files.
 *
 * @author Adam Nielsen <a.nielsen@shikadi.net>
 * @author Vitaliy Filippov <vitalif@mail.ru>
 * @copyright Copyright © 2009 Adam Nielsen
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * @file
 * @ingroup Media
 */
class FlvThumbnailImage extends ThumbnailImage
{
    function __construct($file, $url, $width, $height, $path = false, $page = false)
    {
        parent::__construct($file, $url, $width, $height, $path, $page);
    }
    function toHtml($options = array())
    {
        global $wgScriptPath;
        $html = parent::toHtml($options);
        $html = str_replace('</a>', '<br /><div style="background-image:url('.$wgScriptPath.'/extensions/FlvHandler/film6x10.gif); background-repeat:repeat-x; width:'.$this->width.'px; height: 10px"></div></a>', $html);
        return $html;
    }
}

/**
 * @ingroup Media
 */
class FlvPlayCode extends MediaTransformOutput
{
    /**
     * Constructor
     */
    function FlvPlayCode ($file, $url, $width, $height, $path = false, $page = false)
    {
        global $wgMinFLVSize;
        $this->file = $file;
        $this->url = $url;
        $this->width = 0+$width;
        $this->height = 0+$height;
        $this->width = round($this->width);
        $this->height = round($this->height);
        $this->path = $path;
        $this->page = $page;
    }

    /**
     * Return HTML <object ... /> tag for the flash video player code.
     */
    function toHtml($options = array())
    {
        if (count(func_get_args()) == 2)
            throw new MWException(__METHOD__ .' called in the old style');

        global $wgFlowPlayer, $wgScriptPath, $wgServer, $wgMinFLVSize;

        // Default address of Flash video playing applet
        if (empty($wgFlowPlayer))
            $wgFlowPlayer = 'extensions/FlvHandler/flowplayer/flowplayer-3.0.3.swf';
        if (!preg_match('#^([a-z]+:/)?/#is', $wgFlowPlayer) &&
            (!strlen($wgScriptPath) || substr($wgFlowPlayer, 0, strlen($wgScriptPath)) != $wgScriptPath))
            $wgFlowPlayer = $wgScriptPath . '/'. $wgFlowPlayer;

        $prefix = '<div>';
        $postfix = '</div>';
        if (!empty($options['align']))
        {
            switch ($options['align'])
            {
                case 'center': $className = 'center'; break;
                case 'left': $className = 'floatleft'; break;
                case 'right': $className = 'floatright'; break;
                default: $className = 'floatnone'; break;
            }
            $prefix = '<div class="' . $className . '">';
        }

        $strURL = urlencode($this->file->getFullUrl());

        /* Generate a thumbnail to display in the video window before the user
         * clicks the play button. */
        $thumb = $this->file->transform(array(
            'width' => $this->width,
            'height' => $this->height,
            'makeflvthumbnail' => true,
        ));
        if ($thumb->isError())
            $prefix .= $thumb->toHtml();
        $strThumbURL = $thumb->getUrl();
        if (substr($strThumbURL, 0, strlen($wgServer)) != $wgServer)
            $strThumbURL = $wgServer . $strThumbURL;
        $strThumbURL = urlencode($strThumbURL);

        $strConfig = 'config={"playlist":[ ';
        if ($strThumbURL)
            $strConfig .= '{"url":"' . $strThumbURL . '", "autoPlay":true}, ';
        $strConfig .= '{"url":"' . $strURL . '","autoPlay":false,"fadeInSpeed":0} ] }';

        $w = $this->width;
        $h = $this->height;

        return <<<EOF
$prefix<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$w" height="$h">
    <param name="movie" value="$wgFlowPlayer" />
    <param name="allowfullscreen" value="true" />
    <param name="flashvars" value='$strConfig' />
    <embed type="application/x-shockwave-flash" width="$w" height="$h"
        allowfullscreen="true"
        src="$wgFlowPlayer"
        flashvars='$strConfig' />
</object>$postfix
EOF;
    }
}

/**
 * @ingroup Media
 */
class FlvImageHandler extends ImageHandler
{
    function isEnabled()
    {
        global $wgFLVProbe;
        if (!isset($wgFLVProbe))
        {
            wfDebug("\$wgFLVProbe is invalid, disabling FLV preview frames.\n");
            return false;
        }
        else
            return true;
    }

    function getImageSize($image, $filename)
    {
        global $wgFLVProbe, $wgFLVConverterPath;
        wfProfileIn(__METHOD__);
        if(isset($wgFLVProbe['cmd']))
        {
            $cmd = str_replace(
                array('$path/', '$input'),
                array($wgFLVConverterPath ? wfEscapeShellArg("$wgFLVConverterPath/") : "",
                       wfEscapeShellArg($filename)),
                $wgFLVProbe['cmd']) . " 2>&1";
            wfDebug(__METHOD__.": $cmd\n");
            $out = wfShellExec($cmd, $retval);

            if (preg_match($wgFLVProbe['regex'], $out, $matches))
                return array($matches[1], $matches[2]); // width, height
            else
                wfDebug(__METHOD__ . ': Unable to extract video dimensions from probe output: ' . $out . "\n");
        }
        wfDebug(__METHOD__ . ": No probe function defined, .flv previews unavailable.\n");
        wfProfileOut(__METHOD__);
        return false;
    }

    function mustRender($file)
    {
        return true;
    }

    function normaliseParams($image, &$params)
    {
        if (!parent::normaliseParams($image, $params))
            return false;

        $params['physicalWidth'] = $params['width'];
        $params['physicalHeight'] = $params['height'];
        return true;
    }

    function doTransform($image, $dstPath, $dstUrl, $params, $flags = 0)
    {
        global $wgFLVConverterPath, $wgMinFLVSize;

        if (!$this->normaliseParams($image, $params))
            return new TransformParameterError($params);

        $width = $params['physicalWidth'];
        $height = $params['physicalHeight'];
        $srcPath = $image->getPath();

        $class = 'FlvPlayCode';
        /* makeflvthumbnail=true is used by FlvPlayCode::toHtml() */
        /* imagegallery=true is used by ImageGallery::toHtml() */
        /* imagehistory=true is used by ImagePage::imageHistoryLine() */
        if (!empty($params['makeflvthumbnail']) ||
            !empty($params['imagegallery']) ||
            !empty($params['imagehistory']))
            $class = 'FlvThumbnailImage';

        if ($flags & self::TRANSFORM_LATER)
            return new $class($image, $dstUrl, $width, $height, $dstPath);

        if (!wfMkdirParents(dirname($dstPath)))
            return new MediaTransformError('thumbnail_error', $width, $height,
                wfMsg('thumbnail_dest_directory'));

        wfLoadExtensionMessages('FlvHandler');

        $err = $this->makeFFmpegThumbnail($srcPath, $dstPath, $width, $height);

        if ($err != '')
            return new MediaTransformError('thumbnail_error', $width, $height, $err);
        else
            return new $class($image, $dstUrl, $width, $height, $dstPath);
    }

    function makeFFmpegThumbnail($srcPath, $dstPath, $width, $height)
    {
        global $wgFLVConverterPath, $wgMinFLVSize;
        wfProfileIn(__METHOD__);
        /* Frame count to be extracted onto thumbnail image:
           4 for big thumbnails, 1 for small thumbnails */
        $n = $width >= $wgMinFLVSize[0] && $height >= $wgMinFLVSize[1] ? 2 : 1;
        $ny = $nx = $n;
        $width = intval($width);
        $height = intval($height);
        $input = wfEscapeShellArg($srcPath);
        $ffmpeg = $wgFLVConverterPath ? wfEscapeShellArg("$wgFLVConverterPath/")."ffmpeg" : "ffmpeg";
        /* Find video duration */
        $probe = wfShellExec("$ffmpeg -i $input 2>&1");
        if (!preg_match('/Duration: (\d+):(\d+):(\d+)\.(\d+)/', $probe, $m))
        {
            wfDebug("No Duration: ... found in:\n$probe\n");
            return wfMsgExt('flv-error-full-info', 'parseinline',
                $wgLang->formatNum($width),
                $wgLang->formatNum($height)) . $err;
        }
        $duration = $m[1]*3600 + $m[2]*60 + $m[3] + $m[4]/100;
        if ($ny < 2 && $nx < 2)
        {
            /* Extract one frame */
            $s = sprintf("%.2f", $duration*0.1);
            $cmd = "$ffmpeg -loglevel debug -ss '$s' -i $input -vframes 1 -f image2 -y ".wfEscapeShellArg($dstPath)." 2>&1";
            $err = wfShellExec($cmd, $retval);
            if ($retval != 0)
            {
                wfDebug("$cmd failed:\n$err\n");
                return wfMsgExt('flv-error-full-info', 'parseinline',
                    $wgLang->formatNum($width),
                    $wgLang->formatNum($height)) . $err;
            }
            if (file_exists($dst) && ($gd = imagecreatefromjpeg($dstPath)))
            {
                /* Resample the frame */
                $gd1 = imagecreatetruecolor($width, $height);
                imagecopyresampled($gd1, $gd, 0, 0, 0, 0, $width, $height, imagesx($gd), imagesy($gd));
                imagejpeg($gd1, $dstPath);
                imagedestroy($gd);
                imagedestroy($gd1);
            }
            else
            {
                wfDebug("FLVHandler: Failed running $cmd, output file does not exist. Output was:\n$err");
                return "$dstPath not found";
            }
        }
        else
        {
            /* Extract, resample and tile $nx*$ny frames */
            $tmp = tempnam(wfTempDir(), 'flvtn-').'.jpg';
            $gd = imagecreatetruecolor($width, $height);
            for ($i = 0; $i < $ny; $i++)
            {
                for ($j = 0; $j < $nx; $j++)
                {
                    /* Time moment */
                    $s = sprintf("%.2f", (0.5+$j+$i*$nx)/($nx*$ny)*$duration);
                    $cmd = "$ffmpeg -loglevel debug -ss '$s' -i $input -vframes 1 -f image2 -y '$tmp' 2>&1";
                    $err .= wfShellExec($cmd, $retval);
                    if ($retval != 0)
                    {
                        wfDebug("$cmd failed:\n$err\n");
                        return wfMsgExt('flv-error-full-info', 'parseinline',
                            $wgLang->formatNum($width),
                            $wgLang->formatNum($height)) . $err;
                    }
                    if (file_exists($tmp))
                    {
                        if ($framegd = imagecreatefromjpeg($tmp))
                        {
                            /* Resample each frame using GD, because ffmpeg wants even frame sizes */
                            imagecopyresampled(
                                $gd, $framegd, intval($j/$nx*$width), intval($i/$ny*$height), 0, 0,
                                intval($width/$nx), intval($height/$ny), imagesx($framegd), imagesy($framegd)
                            );
                            imagedestroy($framegd);
                        }
                        unlink($tmp);
                    }
                    else
                        wfDebug("FLVHandler: Failed running $cmd, output file does not exist. Output was:\n$err");
                }
            }
            imagejpeg($gd, $dstPath);
            imagedestroy($gd);
        }
        wfProfileOut(__METHOD__);
        return '';
    }

    function getThumbType($ext, $mime)
    {
        return array('jpg', 'image/jpeg');
    }

    function getLongDesc($file)
    {
        global $wgLang;
        wfLoadExtensionMessages('FlvHandler');
        return wfMsgExt('flv-long-desc', 'parseinline',
            $wgLang->formatNum($file->getWidth()),
            $wgLang->formatNum($file->getHeight()),
            $wgLang->formatSize($file->getSize()));
    }
}
