<?php

if (!defined('MEDIAWIKI'))
    die();

/**#@+
 * An image handler which adds support for Flash video (.flv) files.
 *
 * @addtogroup Extensions
 *
 * @link http://www.mediawiki.org/wiki/Extension:FlvHandler Documentation
 * @link http://www.shikadi.net/mediawiki/FlvHandler/FlvHandler-r3.zip Release 3
 *
 * @author Adam Nielsen <a.nielsen@shikadi.net>
 * @copyright Copyright © 2009 Adam Nielsen
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

// Extension credits that will show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
    'name'         => 'FLV Image Handler',
    'version'      => 'r2',
    'author'       => 'Adam Nielsen',
    'url'          => 'http://www.mediawiki.org/wiki/Extension:FlvHandler',
    'description'  => 'Allow Flash Video (.flv) files to be used in standard image tags (e.g. <nowiki>[[Image:Movie.flv]]</nowiki>)',
    'descriptionmsg' => 'flvhandler_desc'
);

// Register the media handler
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['FlvHandler'] = $dir . 'FlvHandler.i18n.php';
$wgAutoloadClasses['FlvImageHandler'] = $dir . 'FlvImageHandler.php';
$wgMediaHandlers['video/x-flv'] = 'FlvImageHandler';

// Commands to extract still frames out of the FLV files
if (!$wgFLVConverters) $wgFLVConverters = array();
// Useful hack: 'ffmpeg' => '$path/ffmpeg -i $input -ss 0 -vframes 1 -f image2 $output.png && convert $output.png -resize $widthx$height $output && rm $output.png'
if (!$wgFLVConverters['ffmpeg']) $wgFLVConverters['ffmpeg'] = '$path/ffmpeg -vcodec png -i $input -ss 0 -vframes 1 -s $widthx$height -f image2 $output';
if (!$wgFLVConverters['ffmpeg4i']) $wgFLVConverters['ffmpeg4i'] = $dir.'ffmpeg4i $input $width $height $output 2';

// Probe command (to get video width and height.)  'regex' is run over the
// command's output to get the dimensions.
if (!$wgFLVProbes) $wgFLVProbes = array();
if (!$wgFLVProbes['ffmpeg']) $wgFLVProbes['ffmpeg'] = array(
    'cmd' => '$path/ffmpeg -i $input',
    'regex' => '/Stream.*Video.* (\d+)x(\d+)/'  // [1] == width, [2] == height
);
if (!$wgFLVProbes['ffmpeg4i']) $wgFLVProbes['ffmpeg4i'] = array(
    'cmd' => '$path/ffmpeg -i $input',
    'regex' => '/Stream.*Video.* (\d+)x(\d+)/'  // [1] == width, [2] == height
);

// Pick one of the above as the converter to use
if (empty($wgFLVConverter)) $wgFLVConverter = 'ffmpeg';

// If not in the executable PATH, specify
if (empty($wgFLVConverterPath)) $wgFLVConverterPath = '';

// Minimum size for the flash player (width,height). Used to make sure the
// controls don't get all squashed up on really small .flv movies.
if (empty($wgMinFLVSize)) $wgMinFLVSize = array(180, 180);

?>
