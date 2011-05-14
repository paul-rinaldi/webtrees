<?php
// Class that defines a media object
//
// webtrees: Web based Family History software
// Copyright (C) 2011 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2002 to 2009  PGV Development Team.  All rights reserved.
//
// Modifications Copyright (c) 2010 Greg Roach
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//
// @version $Id$

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

class WT_Media extends WT_GedcomRecord {
	var $title         =null;
	var $file          =null;
	var $note          =null;
	var $serverfilename='';
	var $fileexists    =false;
	var $thumbfilename = null;
	var $thumbserverfilename = '';
	var $thumbfileexists = false;

	// Create a Media object from either raw GEDCOM data or a database row
	public function __construct($data) {
		if (is_array($data)) {
			// Construct from a row from the database
			$this->title=$data['m_titl'];
			$this->file =$data['m_file'];
		} else {
			// Construct from raw GEDCOM data
			$this->title = get_gedcom_value('TITL', 1, $data);
			if (empty($this->title)) {
				$this->title = get_gedcom_value('TITL', 2, $data);
			}
			$this->file = get_gedcom_value('FILE', 1, $data);
		}
		if (empty($this->title)) $this->title = $this->file;

		parent::__construct($data);
	}

	// Implement media-specific privacy logic ...
	protected function _canDisplayDetailsByType($access_level) {
		// Hide media objects if they are attached to private records
		$linked_ids=WT_DB::prepare(
			"SELECT l_from FROM `##link` WHERE l_to=? AND l_file=?"
		)->execute(array($this->xref, $this->ged_id))->fetchOneColumn();
		foreach ($linked_ids as $linked_id) {
			$linked_record=WT_GedcomRecord::getInstance($linked_id);
			if ($linked_record && !$linked_record->canDisplayName($access_level)) {
				return false;
			}
		}

		// ... otherwise apply default behaviour
		return parent::_canDisplayDetailsByType($access_level);
	}

	/**
	 * get the media note from the gedcom
	 * @return string
	 */
	public function getNote() {
		if (is_null($this->note)) {
			$this->note=get_gedcom_value('NOTE', 1, $this->getGedcomRecord());
		}
		return $this->note;
	}

	/**
	 * get the media icon filename
	 * @return string
	 */
	public function getMediaIcon() {
		return media_icon_file($this->file);
	}

	/**
	 * get the main media file name
	 * @return string
	 */
	public function getFilename() {
		return $this->file;
	}

	/**
	 * get the relative file path of the image on the server
	 * @param which string - specify either 'main' or 'thumb'
	 * @return string
	 */
	public function getLocalFilename($which='main') {
		if ($which=='main') {
			return check_media_depth($this->file);
		} else {
			// this is a convenience method
			return $this->getThumbnail(false);
		}
	}

	/**
	 * get the file name on the server, either in the standard or protected directory
	 * @param which string - specify either 'main' or 'thumb'
	 * @return string
	 */
	public function getServerFilename($which='main') {
		if ($which=='main') {
			if ($this->serverfilename) return $this->serverfilename;
			$localfilename = $this->getLocalFilename();
			if (!empty($localfilename) && !$this->isFileExternal()) {
				if (file_exists($localfilename)) {
					// found image in unprotected directory
					$this->fileexists = 2;
					$this->serverfilename = $localfilename;
					return $this->serverfilename;
				}
				$protectedfilename = get_media_firewall_path($localfilename);
				if (file_exists($protectedfilename)) {
					// found image in protected directory
					$this->fileexists = 3;
					$this->serverfilename = $protectedfilename;
					return $this->serverfilename;
				}
			}
			// file doesn't exist or is external, return the standard localfilename for backwards compatibility
			$this->fileexists = false;
			$this->serverfilename = $localfilename;
			return $this->serverfilename;
		} else {
			if (!$this->thumbfilename) $this->getThumbnail(false);
			return $this->thumbserverfilename;
		}
	}

	/**
	 * check if the file exists on this server
	 * @param which string - specify either 'main' or 'thumb'
	 * @return boolean
	 */
	public function fileExists($which='main') {
		if ($which=='main') {
			if (!$this->serverfilename) $this->getServerFilename();
			return $this->fileexists;
		} else {
			if (!$this->thumbfilename) $this->getThumbnail(false);
			return $this->thumbfileexists;
		}
	}
	/**
	 * determine if the file is an external url
	* operates on the main url
	 * @return boolean
	 */
	public function isFileExternal() {
		return isFileExternal($this->getLocalFilename());
	}

	/**
	 * get the thumbnail filename
	 * @return string
	 */
	public function getThumbnail($generateThumb = true) {
		if ($this->thumbfilename) return $this->thumbfilename;

		$localfilename = thumbnail_file($this->getLocalFilename(),$generateThumb);
		// Note that localfilename could be in WT_IMAGES
		$this->thumbfilename = $localfilename;
		if (!empty($localfilename) && !$this->isFileExternal()) {
			if (file_exists($localfilename)) {
				// found image in unprotected directory
				$this->thumbfileexists = 2;
				$this->thumbserverfilename = $localfilename;
				return $this->thumbfilename;
			}
			$protectedfilename = get_media_firewall_path($localfilename);
			if (file_exists($protectedfilename)) {
				// found image in protected directory
				$this->thumbfileexists = 3;
				$this->thumbserverfilename = $protectedfilename;
				return $this->thumbfilename;
			}
		}

		// this should never happen, since thumbnail_file will return something in WT_IMAGES if a thumbnail can't be found
		$this->thumbfileexists = false;
		$this->thumbserverfilename = $localfilename;
		return $this->thumbfilename;
	}


	/**
	 * get the media file size in KB
	 * @param which string - specify either 'main' or 'thumb'
	 * @return string
	 */
	public function getFilesize($which='main') {
		$size = $this->getFilesizeraw($which);
		if ($size) $size=$size/1024;
		return /* I18N: size of file in KB */ WT_I18N::translate('%s KB', WT_I18N::number($size,2));
	}

	/**
	 * get the media file size, unformatted
	 * @param which string - specify either 'main' or 'thumb'
	 * @return number
	 */
	public function getFilesizeraw($which='main') {
		if ($this->fileExists($which)) return @filesize($this->getServerFilename($which));
		return 0;
	}

	/**
	 * get filemtime for the media file
	 * @param which string - specify either 'main' or 'thumb'
	 * @return number
	 */
	public function getFiletime($which='main') {
		if ($this->fileExists($which)) return @filemtime($this->getServerFilename($which));
		return 0;
	}

	/**
	 * generate an etag specific to this media item and the current user
	 * @param which string - specify either 'main' or 'thumb'
	 * @return number
	 */
	public function getEtag($which='main') {
		// setup the etag.  use enough info so that if anything important changes, the etag won't match
		global $SHOW_NO_WATERMARK;
		if ($this->isFileExternal()) {
			// etag not really defined for external media
			return '';
		}
		$etag_string = basename($this->getServerFilename($which)).$this->getFiletime($which).WT_GEDCOM.WT_USER_ACCESS_LEVEL.$SHOW_NO_WATERMARK;
		$etag_string = dechex(crc32($etag_string));
		return ($etag_string);
	}


	/**
	 * get the media type from the gedcom
	 * @return string
	 */
	public function getMediatype() {
		$mediaType = strtolower(get_gedcom_value('FORM:TYPE', 2, $this->getGedcomRecord()));
		return $mediaType;
	}

	/**
	 * get image properties
	 * @param which string - specify either 'main' or 'thumb'
	 * @param addWidth int - amount to add to width
	 * @param addHeight int - amount to add to height
	 * @return array
	 */
	public function getImagesize($which='main',$addWidth=0,$addHeight=0) {
		global $THUMBNAIL_WIDTH, $TEXT_DIRECTION;
		$imgsize = array();
		if ($this->fileExists($which)) {
			$imgsize=@getimagesize($this->getServerFilename($which)); // [0]=width [1]=height [2]=filetype ['mime']=mimetype
			if (is_array($imgsize) && !empty($imgsize['0'])) {
				// this is an image
				$imgsize[0]=$imgsize[0]+0;
				$imgsize[1]=$imgsize[1]+0;
				$imgsize['adjW']=$imgsize[0]+$addWidth; // adjusted width
				$imgsize['adjH']=$imgsize[1]+$addHeight; // adjusted height
				$imageTypes=array('','GIF','JPG','PNG','SWF','PSD','BMP','TIFF','TIFF','JPC','JP2','JPX','JB2','SWC','IFF','WBMP','XBM');
				$imgsize['ext']=$imageTypes[0+$imgsize[2]];
				// this is for display purposes, always show non-adjusted info
				$imgsize['WxH']=/* I18N: image dimensions, width x height */ WT_I18N::translate('%1$s &times; %2$s pixels', WT_I18N::number($imgsize['0']), WT_I18N::number($imgsize['1']));
				$imgsize['imgWH']=' width="'.$imgsize['adjW'].'" height="'.$imgsize['adjH'].'" ';
				if ( ($which=='thumb') && ($imgsize['0'] > $THUMBNAIL_WIDTH) ) {
					// don't let large images break the dislay
					$imgsize['imgWH']=' width="'.$THUMBNAIL_WIDTH.'" ';
				}
			}
		}

		if (!is_array($imgsize) || empty($imgsize['0'])) {
			// this is not an image, OR the file doesn't exist OR it is a url
			$imgsize[0]=0;
			$imgsize[1]=0;
			$imgsize['adjW']=0;
			$imgsize['adjH']=0;
			$imgsize['ext']='';
			$imgsize['mime']='';
			$imgsize['WxH']='';
			$imgsize['imgWH']='';
			if ($this->isFileExternal($which)) {
				// don't let large external images break the dislay
				$imgsize['imgWH']=' width="'.$THUMBNAIL_WIDTH.'" ';
			}
		}

		if (empty($imgsize['mime'])) {
			// this is not an image, OR the file doesn't exist OR it is a url
			// set file type equal to the file extension - can't use parse_url because this may not be a full url
			$exp = explode('?', $this->file);
			$pathinfo = pathinfo($exp[0]);
			$imgsize['ext']=@strtoupper($pathinfo['extension']);
			// all mimetypes we wish to serve with the media firewall must be added to this array.
			$mime=array('DOC'=>'application/msword', 'MOV'=>'video/quicktime', 'MP3'=>'audio/mpeg', 'PDF'=>'application/pdf',
			'PPT'=>'application/vnd.ms-powerpoint', 'RTF'=>'text/rtf', 'SID'=>'image/x-mrsid', 'TXT'=>'text/plain', 'XLS'=>'application/vnd.ms-excel',
			'WMV'=>'video/x-ms-wmv');
			if (empty($mime[$imgsize['ext']])) {
				// if we don't know what the mimetype is, use something ambiguous
				$imgsize['mime']='application/octet-stream';
				if ($this->fileExists($which)) {
					// alert the admin if we cannot determine the mime type of an existing file
					// as the media firewall will be unable to serve this file properly
					AddToLog('Media Firewall error: >Unknown Mimetype< for file >'.$this->file.'<', 'media');
				}
			} else {
				$imgsize['mime']=$mime[$imgsize['ext']];
			}
		}
		return $imgsize;
	}

	// Generate a URL to this record, suitable for use in HTML
	public function getHtmlUrl() {
		return parent::_getLinkUrl('mediaviewer.php?mid=', '&amp;');
	}
	// Generate a URL to this record, suitable for use in javascript, HTTP headers, etc.
	public function getRawUrl() {
		return parent::_getLinkUrl('mediaviewer.php?mid=', '&');
	}


	/**
	 * Generate a URL directly to the media file, suitable for use in HTML
	 * @param which string - specify either 'main' or 'thumb'
	 * @param separator string - specify either '&amp;' or '&'
	 * @return string
	 */
	public function getHtmlUrlDirect($which='main', $download=false, $separator = '&amp;') {

	 	if ($this->isFileExternal()) {
			// this is an external file, do not try to access it through the media firewall
			if ($separator == '&') {
				return rawurlencode($this->getFilename());
			} else {
				return $this->getFilename();
			}
		} else if ($this->ged_id) {
			// this file has gedcom record
			if ($this->fileExists($which) == 3) {
				// file is in protected media directory, access through media firewall
				// 'cb' is 'cache buster', so clients will make new request if anything significant about the user or the file changes
				$thumbstr = ($which=='thumb') ? $separator.'thumb=1' : '';
				$downloadstr = ($download) ? $separator.'dl=1' : '';
				return 'mediafirewall.php?mid='.$this->getXref().$thumbstr.$downloadstr.$separator.'ged='.rawurlencode(get_gedcom_from_id($this->ged_id)).$separator.'cb='.$this->getEtag($which);
			} else {
				// file is in standard media directory (or doesn't exist), no need to use media firewall script
				// definitely don't want icons defined in $WT_IMAGES going through the media firewall
				if ($separator == '&') {
					return rawurlencode($this->getLocalFilename($which));
				} else {
					return $this->getLocalFilename($which);
				}
			}
		} else {
			// this file is not in the gedcom
			if ($this->fileExists($which) == 3) {
				// file is in protected media directory, access through media firewall
				$downloadstr = ($download) ? $separator.'dl=1' : '';
				return 'mediafirewall.php?filename='.$this->getLocalFilename($which).$downloadstr.$separator.'cb='.$this->getEtag($which);
			} else {
				// file is in standard media directory (or doesn't exist), no need to use media firewall script
				if ($separator == '&') {
					return rawurlencode($this->getLocalFilename($which));
				} else {
					return $this->getLocalFilename($which);
				}
			}
		}
	}
	// Generate a URL directly to the media file, suitable for use in javascript, HTTP headers, etc.
	public function getRawUrlDirect($which='main', $download=false) {
		return $this->getHtmlUrlDirect($which, $download, '&');
	}

	/**
	 * builds html snippet with javascript, etc appropriate to view the media file
	 * @return string, suitable for use inside an a tag: '<a href="'.$this->getHtmlUrlSnippet().'">';
	 */
	public function getHtmlUrlSnippet($obeyViewerOption=true) {

		$name=PrintReady(htmlspecialchars($this->getFullName()));
		$urltype = get_url_type($this->getLocalFilename());
		$notes=($this->getNote()) ? print_fact_notes("1 NOTE ".$this->getNote(), 1, true, true) : '';

		// -- Determine the correct URL to open this media file
		while (true) {
			if (WT_USE_LIGHTBOX && (WT_THEME_DIR!=WT_THEMES_DIR.'_administration/')) {
				// Lightbox is installed
				require_once WT_ROOT.WT_MODULES_DIR.'lightbox/lb_defaultconfig.php';
				switch ($urltype) {
				case 'url_flv':
					$url = 'js/jw_player/flvVideo.php?flvVideo='.$this->getRawUrlDirect('main') . "\" rel='clearbox(500, 392, click)' rev=\"" . $this->getXref() . "::" . get_gedcom_from_id($this->ged_id) . "::" . $name . "::" . htmlspecialchars($notes);
					break 2;
				case 'local_flv':
					$url = 'js/jw_player/flvVideo.php?flvVideo='.WT_SERVER_NAME.WT_SCRIPT_PATH.$this->getRawUrlDirect('main') . "\" rel='clearbox(500, 392, click)' rev=\"" . $this->getXref() . "::" . get_gedcom_from_id($this->ged_id) . "::" . $name . "::" . htmlspecialchars($notes);
					break 2;
				case 'url_wmv':
					$url = 'js/jw_player/wmvVideo.php?wmvVideo='.$this->getRawUrlDirect('main') . "\" rel='clearbox(500, 392, click)' rev=\"" . $this->getXref() . "::" . get_gedcom_from_id($this->ged_id) . "::" . $name . "::" . htmlspecialchars($notes);
					break 2;
				case 'local_audio':
				case 'local_wmv':
					$url = 'js/jw_player/wmvVideo.php?wmvVideo='.WT_SERVER_NAME.WT_SCRIPT_PATH.$this->getRawUrlDirect('main') . "\" rel='clearbox(500, 392, click)' rev=\"" . $this->getXref() . "::" . get_gedcom_from_id($this->ged_id) . "::" . $name . "::" . htmlspecialchars($notes);
					break 2;
				case 'url_image':
				case 'local_image':
					$url = $this->getHtmlUrlDirect('main') . "\" rel=\"clearbox[general]\" rev=\"" . $this->getXref() . "::" . get_gedcom_from_id($this->ged_id) . "::" . $name . "::" . htmlspecialchars($notes);
					break 2;
				case 'url_picasa':
				case 'url_page':
				case 'url_pdf':
				case 'url_other':
				case 'local_page':
				case 'local_pdf':
				// case 'local_other':
					$url = $this->getHtmlUrlDirect('main') . "\" rel='clearbox({$LB_URL_WIDTH}, {$LB_URL_HEIGHT}, click)' rev=\"" . $this->getXref() . "::" . get_gedcom_from_id($this->ged_id) . "::" . $name . "::" . htmlspecialchars($notes);
					break 2;
				case 'url_streetview':
					// need to call getHtmlForStreetview() instead of getHtmlUrlSnippet()
					break 2;
				}
			}

			// Lightbox is not installed or Lightbox is not appropriate for this media type
			switch ($urltype) {
			case 'url_flv':
				$url = "javascript:;\" onclick=\" var winflv = window.open('".'js/jw_player/flvVideo.php?flvVideo='.$this->getRawUrlDirect('main') . "', 'winflv', 'width=500, height=392, left=600, top=200'); if (window.focus) {winflv.focus();}";
				break 2;
			case 'local_flv':
				$url = "javascript:;\" onclick=\" var winflv = window.open('".'js/jw_player/flvVideo.php?flvVideo='.WT_SERVER_NAME.WT_SCRIPT_PATH.$this->getRawUrlDirect('main') . "', 'winflv', 'width=500, height=392, left=600, top=200'); if (window.focus) {winflv.focus();}";
				break 2;
			case 'url_wmv':
				$url = "javascript:;\" onclick=\" var winwmv = window.open('".'js/jw_player/wmvVideo.php?wmvVideo='.$this->getRawUrlDirect('main') . "', 'winwmv', 'width=500, height=392, left=600, top=200'); if (window.focus) {winwmv.focus();}";
				break 2;
			case 'local_wmv':
			case 'local_audio':
				$url = "javascript:;\" onclick=\" var winwmv = window.open('".'js/jw_player/wmvVideo.php?wmvVideo='.WT_SERVER_NAME.WT_SCRIPT_PATH.$this->getRawUrlDirect('main') . "', 'winwmv', 'width=500, height=392, left=600, top=200'); if (window.focus) {winwmv.focus();}";
				break 2;
			case 'url_image':
			case 'local_image':
				$imgsize = $this->getImagesize('main',40,150);
				$url = "javascript:;\" onclick=\"var winimg = window.open('".$this->getRawUrlDirect('main')."', 'winimg', 'width=".$imgsize['adjW'].", height=".$imgsize['adjH'].", left=200, top=200'); if (window.focus) {winimg.focus();}";
				break 2;
			case 'url_picasa':
			case 'url_page':
			case 'url_pdf':
			case 'url_other':
			case 'local_other';
				$url = "javascript:;\" onclick=\"var winurl = window.open('".$this->getRawUrlDirect('main')."', 'winurl', 'width=900, height=600, left=200, top=200'); if (window.focus) {winurl.focus();}";
				break 2;
			case 'local_page':
			case 'local_pdf':
				$url = "javascript:;\" onclick=\"var winurl = window.open('".$this->getRawUrlDirect('main')."', 'winurl', 'width=900, height=600, left=200, top=200'); if (window.focus) {winurl.focus();}";
				break 2;
			case 'url_streetview':
				// need to call getHtmlForStreetview() instead of getHtmlUrlSnippet()
				break 2;
			}
			if ($USE_MEDIA_VIEWER && $obeyViewerOption) {
				$url = $this->getHtmlUrl();
			} else {
				$imgsize = $this->getImagesize('main',40,150);
				$url = "javascript:;\" onclick=\"return openImage('".$this->getRawUrlDirect('main')."', ".$imgsize['adjW'].", ".$imgsize['adjH'].");";
			}
			break;
		}

		return $url;
	}

	/**
	 * if this is a Google Streetview url, return the HTML required to display it
	* if not a Google Streetview url, return ''
	* @return string
	 */
	public function getHtmlForStreetview() {
		if (strpos($this->getHtmlUrlDirect('main'), 'http://maps.google.')===0) {
			return '<iframe style="float:left; padding:5px;" width="264" height="176" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="'.$this->getHtmlUrlDirect('main').'&amp;output=svembed"></iframe>';
		}
		return '';
	}

	/**
	 * returns the complete HTML needed to render a thumbnail image that is linked to the main image
	* @return string
	 */
	public function displayMedia($download=false, $obeyViewerOption=true) {
		global $TEXT_DIRECTION,$SHOW_MEDIA_DOWNLOAD;
		if ($this->getHtmlForStreetview()) {
			$output  = $this->getHtmlForStreetview();
		} else {

			$oktolink = $this->isFileExternal() || $this->fileExists('main');
			$name=PrintReady(htmlspecialchars($this->getFullName()));
			$imgsizeThumb = $this->getImagesize('thumb');
			$output = '';

			if ($oktolink) $output .= '<a href="'.$this->getHtmlUrlSnippet($obeyViewerOption).'">';
			$output .= '<img src="'.$this->getHtmlUrlDirect('thumb').'" '.$imgsizeThumb['imgWH'].' border="none" align="'.($TEXT_DIRECTION=="rtl" ? "right":"left").'" class="thumbnail"';
			$output .= ' alt="'.$name.'" title="'.$name.'" />';
			if ($oktolink) {
				$output .= '</a>';
				if ($download && $SHOW_MEDIA_DOWNLOAD) {
					$output .= '<div><a href="'.$this->getHtmlUrlDirect('main', true).'">'.WT_I18N::translate('Download File').'</a></div>';
				}
			} else {
				$output .= '<div class="error">'.WT_I18N::translate('File not found.').'</div>';
			}
		}
		return $output;
	}

	/**
	 * check if the given Media object is in the objectlist
	 * @param Media $obje
	 * @return mixed  returns the ID for the for the matching media or null if not found
	 */
	static function in_obje_list($obje, $ged_id) {
		return
			WT_DB::prepare("SELECT m_media FROM `##media` WHERE m_file=? AND m_titl LIKE ? AND m_gedfile=?")
			->execute(array($obje->file, $obje->title, $ged_id))
			->fetchOne();
	}

	// If this object has no name, what do we call it?
	public function getFallBackName() {
		if ($this->canDisplayDetails()) {
			return utf8_strtoupper(basename($this->file));
		} else {
			return $this->getXref();
		}
	}

	// Get an array of structures containing all the names in the record
	public function getAllNames() {
		if (strpos($this->getGedcomRecord(), "\n1 TITL ")) {
			// Earlier gedcom versions had level 1 titles
			return parent::_getAllNames('TITL', 1);
		} else {
			// Later gedcom versions had level 2 titles
			return parent::_getAllNames('TITL', 2);
		}
	}

	// Extra info to display when displaying this record in a list of
	// selection items or favorites.
	public function format_list_details() {
		ob_start();
		print_media_links('1 OBJE @'.$this->getXref().'@', 1, $this->getXref());
		return ob_get_clean();
	}
}
