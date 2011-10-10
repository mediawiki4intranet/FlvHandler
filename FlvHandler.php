<?php

if (!defined('MEDIAWIKI'))
    die();

/**#@+
 * An image handler which adds support for Flash video (.flv) and MP4 H.264/AAC files.
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
    'description'  => 'Allow Flash Video (.flv) and MP4 H.264/AAC files to be used in standard image tags (e.g. <nowiki>[[Image:Movie.flv]]</nowiki>)',
    'descriptionmsg' => 'flvhandler_desc'
);

// Register the media handler
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['FlvHandler'] = $dir . 'FlvHandler.i18n.php';
$wgAutoloadClasses['FlvImageHandler'] = $dir . 'FlvImageHandler.php';
$wgMediaHandlers['video/x-flv'] = 'FlvImageHandler';
$wgMediaHandlers['video/mp4'] = 'FlvImageHandler';

// Probe command (to get video width and height.)  'regex' is run over the
// command's output to get the dimensions.
if (empty($wgFLVProbe)) $wgFLVProbe = array(
    'cmd' => '$path/ffmpeg -i $input',
    'regex' => '/Stream.*Video.* (\d+)x(\d+)/'  // [1] == width, [2] == height
);

// If not in the executable PATH, specify
if (empty($wgFLVConverterPath)) $wgFLVConverterPath = '';

// Minimum size for the flash player (width,height). Used to make sure the
// controls don't get all squashed up on really small .flv movies.
if (empty($wgMinFLVSize)) $wgMinFLVSize = array(180, 180);

// Add file extensions
$wgFileExtensions[] = 'flv';
$wgFileExtensions[] = 'mp4';
