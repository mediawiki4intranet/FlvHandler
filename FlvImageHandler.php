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
    function FlvThumbnailImage($file, $url, $width, $height, $path = false, $page = false)
    {
        $this->ThumbnailImage($file, $url, $width, $height, $path, $page);
    }
    function toHtml($options = array())
    {
        global $wgScriptPath;
        $html = parent::toHtml($options);
        $html = str_replace('</a>', '<br><div style="background-image:url('.$wgScriptPath.'/extensions/FlvHandler/film6x10.gif); background-repeat:repeat-x; width:'.$this->width.'px; height: 10px"></div></a>', $html);
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
        global $wgFLVConverters, $wgFLVConverter, $wgFLVProbes;
        wfDebug('probes is ' . print_r($wgFLVProbes, true) . "\n");
        if ((!isset($wgFLVConverters[$wgFLVConverter])) || (!isset($wgFLVProbes[$wgFLVConverter])))
        {
            wfDebug("\$wgFLVConverter is invalid, disabling FLV preview frames.\n");
            return false;
        }
        else
            return true;
    }

    function getImageSize($image, $filename)
    {
        global $wgFLVProbes, $wgFLVConverter, $wgFLVConverterPath;
        if(isset($wgFLVProbes[$wgFLVConverter]['cmd']))
        {
            $cmd = str_replace(
                array('$path/', '$input'),
                array($wgFLVConverterPath ? wfEscapeShellArg("$wgFLVConverterPath/") : "",
                       wfEscapeShellArg($filename)),
                $wgFLVProbes[$wgFLVConverter]['cmd']) . " 2>&1";
            wfProfileIn('rsvg');
            wfDebug(__METHOD__.": $cmd\n");
            $out = wfShellExec($cmd, $retval);
            wfProfileOut('rsvg');

            if (preg_match($wgFLVProbes[$wgFLVConverter]['regex'], $out, $matches))
                return array($matches[1], $matches[2]); // width, height
            else
                wfDebug(__METHOD__ . ': Unable to extract video dimensions from ' . $wgFLVConverter . ' output: ' . $out . "\n");
        }
        wfDebug(__METHOD__ . ": No probe function defined, .flv previews unavailable.\n");
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
        global $wgFLVConverters, $wgFLVConverter, $wgFLVConverterPath, $wgMinFLVSize;

        if (!$this->normaliseParams($image, $params))
            return new TransformParameterError($params);

        $clientWidth = $params['width'];
        $clientHeight = $params['height'];
        $physicalWidth = $params['physicalWidth'];
        $physicalHeight = $params['physicalHeight'];
        $srcPath = $image->getPath();

        $class = 'FlvPlayCode';
        /* makeflvthumbnail=true is used by FlvPlayCode::toHtml() */
        /* imagegallery=true is used by ImageGallery::toHtml() */
        /* imagehistory=true is used by ImagePage::imageHistoryLine() */
        if ($params['makeflvthumbnail'] || $params['imagegallery'] || $params['imagehistory'])
            $class = 'FlvThumbnailImage';

        if ($flags & self::TRANSFORM_LATER)
            return new $class($image, $dstUrl, $clientWidth, $clientHeight, $dstPath);

        if (!wfMkdirParents(dirname($dstPath)))
            return new MediaTransformError('thumbnail_error', $clientWidth, $clientHeight,
                wfMsg('thumbnail_dest_directory'));

        $err = false;
        if (isset($wgFLVConverters[$wgFLVConverter]))
        {
            /* Invoke ./ffmpeg4i (or another converter) */
            $n = $clientWidth >= $wgMinFLVSize[0] && $clientHeight >= $wgMinFLVSize[1] ? 2 : 1;
            $cmd = str_replace(
                array('$path/', '$width', '$height', '$input', '$output', '$nx', '$ny'),
                array(
                    $wgFLVConverterPath ? wfEscapeShellArg("$wgFLVConverterPath/") : "",
                    intval($physicalWidth),
                    intval($physicalHeight),
                    wfEscapeShellArg($srcPath),
                    wfEscapeShellArg($dstPath),
                    $n,
                    $n
                ), $wgFLVConverters[$wgFLVConverter]) . " 2>&1";
            wfProfileIn('rsvg');
            wfDebug(__METHOD__.": $cmd\n");
            $err = wfShellExec($cmd, $retval);
            wfProfileOut('rsvg');
        }

        $removed = $this->removeBadFile($dstPath, $retval);
        if ($retval != 0 || $removed)
        {
            /* Try to format error slightly */
            $err = trim($err);
            wfDebugLog('thumbnail',
                sprintf('thumbnail failed on %s: error %d "%s" from "%s"',
                    wfHostname(), $retval, $err, $cmd));

            if (preg_match('#([^\n]*)$#is', $err, $m))
            {
                global $wgLang;
                wfLoadExtensionMessages('FlvHandler');
                $err =
                    $m[1] .
                    wfMsgExt('flv-error-full-info', 'parseinline',
                        $wgLang->formatNum(intval($physicalWidth)),
                        $wgLang->formatNum(intval($physicalHeight))) .
                    $err;
            }
            return new MediaTransformError('thumbnail_error', $clientWidth, $clientHeight, $err);
        }
        else
            return new $class($image, $dstUrl, $clientWidth, $clientHeight, $dstPath);
    }

    function getThumbType($ext, $mime)
    {
        return array('png', 'image/png');
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
