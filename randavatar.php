<?php
/*
Plugin Name: FL3R Randavatar
Version: 1.0
Plugin URI: https://wordpress.org/plugins/fl3r-randavatar/
Description: This plugin provides a large randomly assembled avatar for each user based on their email address. When a user comments on the site, the plugin generates a unique avatar based on his email. There are an impressive number of avatars that can be generated, and it increases at each update of the plugin! 
Author: Armando "FL3R" Fiore
Author URI: https://www.twitter.com/Armando_Fiore
*/

// This plugin uses modified images from original works of Emoji One. The original Emoji One's emoji graphics are free to use for any project, commercial or personal under a "Free Culture" Creative Commons License (CC-BY-SA).

//f: variabile: randavatarX_possible definisce il numero di avatar possibili, cercare nel testo

//f: languages
load_plugin_textdomain('randavatar', NULL, dirname(plugin_basename(__FILE__)) . "/languages");

//Deal with either wp-content/plugins/fl3r-randavatar/
//Assuming randavatarx.php is one directory below /fl3r-randavatar

define('WP_RANDAVATARX_DIR', plugins_url(basename(dirname(__FILE__)).'/avatar/'));
define('WP_RANDAVATARX_DIR_INTERNAL', dirname(__FILE__).'/avatar/');
define('WP_RANDAVATARPARTS_DIR', WP_RANDAVATARX_DIR_INTERNAL.'parts/');
define('WP_RANDAVATARX_MAXWAIT', 5);
define('DEFAULT_RANDAVATARX_RECENTCOMMENTS_CSS',
'ul#randavatarx_recentcomments{list-style:none;}
ul#randavatarx_recentcomments img.randavatarx{float:left;margin: 0 3px 0 0;}
ul#randavatarx_recentcomments{overflow:auto;}
li#recent-comments-with-randavatarxs ul#randavatarx_recentcomments li{clear:left;padding-bottom:5px;}
ul#randavatarx_recentcomments li.recentcomments:before{content:"";} 
.recentcomments a{display:inline !important;padding: 0 !important;margin: 0 !important;}'
);
define('DEFAULT_RANDAVATARX_CSS',
'img.randavatarx{float:left;margin: 1px;}'
);

function randavatarx_menu() {
	if (function_exists('add_options_page')) {
		add_options_page('FL3R Randavatar Control Panel', 'FL3R Randavatar', 1, basename(__FILE__), 'randavatarx_subpanel');
	}
}
class randavatarx{
	//var $whiteParts =  array(); 
	//var $sameColorParts = array();
	//var $specificColorParts = array();
	//var $randomColorParts = array();
	
	//Generated from find_parts_dimensions

	//var $partOptimization=array(
	//'xxx_1.png' => array(array(0,80),array(0,80)),
	//);
	var $startTime;
	var $randavatarx_options;
	function randavatarx(){
		//get the options
		$this->randavatarx_options=$this->get_options();
	}

	function findparts($partsarray){
		$dir=WP_RANDAVATARPARTS_DIR;
		$noparts=true;
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (is_file($dir.$file)){
					$partname=explode('_',$file);
					$partname=$partname[0];
					if (array_key_exists($partname,$partsarray)){
						array_push($partsarray[$partname],$file);
						$noparts=false;
					}
				}
			}
		}
		closedir($dh);
		if ($noparts) return false;
		//sort for consistency across servers
		foreach($partsarray as $key => $value) sort($partsarray[$key]);
		return $partsarray;
	}

	function get_options($check=FALSE){
		if(!isset($this->randavatarx_options)||$check){
			$randavatarX_array=get_option('randavatarX');
			if (!isset($randavatarX_array['size'])||!isset($randavatarX_array['backb'])){
				//Set Default Values Here
				$default_array=array('size'=>80,'backr'=>array(0,0),'backg'=>array(0,0),'backb'=>array(0,0),'sfondo'=>0,'autoadd'=>2,'gravatar'=>0,'artistic'=>1,'greyscale'=>1,'css'=>DEFAULT_RANDAVATARX_CSS);
				add_option('randavatarX',$default_array,'Options used by Randavatar',false);
				$randavatarX_array=$default_array;
			}
			$this->randavatarx_options=$randavatarX_array;
		}
		return $this->randavatarx_options;
	}

	function find_parts_dimensions($text=false){
		$parts_array=array('sfondo' => array(),'arms' => array(),'body' => array(),'eyes' => array(),'mouth' => array(),'extra' => array());
		$parts=$this->findparts($parts_array);
		$bounds=array();
		foreach($parts as $key => $value){
			foreach($value as $part){
				$file=WP_RANDAVATARPARTS_DIR.$part;
				$im=imagecreatefrompng($file);
				$imgw = imagesx($im);
				$imgh = imagesy($im);
				$xbounds=array(999999,0);
				$ybounds=array(999999,0);
				for($i=0;$i<$imgw;$i++){
					for($j=0;$j<$imgh;$j++){
						$rgb=ImageColorAt($im, $i, $j);
						$r = ($rgb >> 16) & 0xFF;
						$g = ($rgb >> 8) & 0xFF;
						$b = $rgb & 0xFF;
						$alpha = ($rgb & 0x7F000000) >> 24;
						$lightness=($r+$g+$b)/3/255;
						if($lightness>.1&&$lightness<.99&&$alpha<115){
							$xbounds[0]=min($xbounds[0],$i);
							$xbounds[1]=max($xbounds[1],$i);
							$ybounds[0]=min($ybounds[0],$j);
							$ybounds[1]=max($ybounds[1],$j);
						}
					}
				}
				$text.="'$part' => array(array(${xbounds[0]},${xbounds[1]}),array(${ybounds[0]},${ybounds[1]})), ";
				$bounds[$part]=array($xbounds,$ybounds);
			}
		}
		if($text) return $text;
		else return $bounds;
	}

	function build_randavatar($seed='',$altImgText='',$img=true,$size='',$write=true,$displaySize='',$gravataron=true){
		if (function_exists("gd_info")&&is_writable(WP_RANDAVATARX_DIR_INTERNAL)){
			// init random seed
			$id=substr(sha1($seed),0,8);
			//use admin email as salt. should be safe
			$filename=substr(sha1($id.substr(get_option('admin_email'),0,5)),0,15).'.png';
			$randavatarX_options=$this->get_options();	
			if ($size=='') $size=$randavatarX_options['size'];
			if($displaySize=='') $displaySize=$size;
			if (!file_exists(WP_RANDAVATARX_DIR_INTERNAL.$filename)){
				if(!isset($this->startTime))$this->startTime=time();
				#make sure nobody waits more than 5 seconds
				if(time()-$this->startTime>WP_RANDAVATARX_MAXWAIT){
					$user=wp_get_current_user();
					#Let it go longer if the user is an admin
					if($user->user_level < 8||time()-$this->startTime>14) return false;
				}

				//check if transparent
				if (array_sum($randavatarX_options['backr'])+array_sum($randavatarX_options['backg'])+array_sum($randavatarX_options['backb'])>0) $transparent=false;
				else $transparent=true;
				
				if($randavatarX_options['artistic']) $parts_array=array('sfondo' => array(),'arms' => array(),'body' => array(),'eyes' => array(),'mouth' => array(),'extra' => array());
				elseif ($randavatarX_options['sfondo']==1&&!$randavatarX_options['artistic']) $parts_array=array('woldsfondo' => array(),'woldextra' => array(),'woldarms' => array(),'woldbody' => array(),'oldeyes' => array(),'oldmouth' => array());
				else $parts_array=array('oldsfondo' => array(),'oldextra' => array(),'oldarms' => array(),'oldbody' => array(),'oldeyes' => array(),'oldmouth' => array());
				
				$parts_order=array_keys($parts_array);
				
				//get possible parts files
				$parts_array=$this->findparts($parts_array);

				if(!$parts_array) return false;
				//set randomness
				$twister=new mid_mersenne_twister(hexdec($id));
				// throw the dice for body parts
				foreach ($parts_order as $part){
					$parts_array[$part]=$parts_array[$part][$twister->array_rand($parts_array[$part])];
				}


				// create backgound
				$file=WP_RANDAVATARPARTS_DIR.'back.png';
				$randavatar =  @imagecreatefrompng($file);
				if(!$randavatar) return false;//something went wrong but don't want to mess up blog layout
				$hue=$twister->real_halfopen();
				$saturation=$twister->rand(25000,100000)/100000;
				//Pick a back color even if transparent to preserve random draws across servers		
				$back = imagecolorallocate($randavatar, $twister->rand($randavatarX_options['backr'][0],$randavatarX_options['backr'][1]), $twister->rand($randavatarX_options['backg'][0],$randavatarX_options['backg'][1]), $twister->rand($randavatarX_options['backb'][0],$randavatarX_options['backb'][1]));
				$lightness=$twister->rand(25000,90000)/100000; //Don't actually user this if artistic but preserves randomness
				if (!$transparent){
					imagefill($randavatar,0,0,$back);
				}

				// add parts
				foreach($parts_order as $part){
					$file=$parts_array[$part];
					$file=WP_RANDAVATARPARTS_DIR.$file;
					$im = @imagecreatefrompng($file);
					if(!$im) return false; //something went wrong but don't want to mess up blog layout
					imageSaveAlpha($im, true);
					if ($randavatarX_options['artistic']&&$randavatarX_options['greyscale']){
						//randomly color body parts
						if($randavatarX_options['sfondo']&&in_array($parts_array[$part],$this->whiteParts)){
							$this->image_whitize($im);
						}
						if($part == 'body'||$part == 'wbody'){
							//imagefill($monster,120,120,$body);
							$this->image_colorize($im,$hue,$saturation,$parts_array[$part]);
						}
//						elseif(in_array($parts_array[$part],$this->sameColorParts)){
//							$this->image_colorize($im,$hue,$saturation,$parts_array[$part]);
//						}elseif(in_array($parts_array[$part],$this->randomColorParts)){
//							$this->image_colorize($im,$twister->real_halfopen(),$twister->rand(25000,100000)/100000,$parts_array[$part]);
//						}elseif(array_key_exists($parts_array[$part],$this->specificColorParts)){
//							$low=$this->specificColorParts[$parts_array[$part]][0]*10000;
//							$high=$this->specificColorParts[$parts_array[$part]][1]*10000;
//							$this->image_colorize($im,$twister->rand($low,$high)/10000,$twister->rand(25000,100000)/100000,$parts_array[$part]);
//						}
					}else{
						if($part == 'oldbody'||$part == 'woldbody'){
							$rgb_color=$this->HSL2hex(array($hue,$saturation,$lightness));
							$body=imagecolorallocate($im, $rgb_color[0], $rgb_color[1], $rgb_color[2]);
							imagefill($im,60,60,$body);
						}
					}
					imagecopy($randavatar,$im,0,0,0,0,120,120);
					imagedestroy($im);
				}
				// going to resize always for now
				$out = @imagecreatetruecolor($size,$size); 
				if (!$out) return false;//something went wrong but don't want to mess up blog layout
				if ($transparent){
					imageSaveAlpha($out,true);
					imageAlphaBlending($out, false);
				}
				imagecopyresampled($out,$randavatar,0,0,0,0,$size,$size,120,120);
				imagedestroy($randavatar);

				if ($write){
						$wrote=@imagepng($out,WP_RANDAVATARX_DIR_INTERNAL.$filename);
						if(!$wrote) return false; //something went wrong but don't want to mess up blog layout
				}else{
					header ("Content-type: image/png");
					imagepng($out);    
				}
				imagedestroy($out);
			}
			//f: crea percorso immagine
			//OLD: $filename=get_option('siteurl').WP_RANDAVATARX_DIR.$filename;
			$filename=WP_RANDAVATARX_DIR.$filename;
			if($randavatarX_options['gravatar']&&$gravataron)
					$filename = "http://www.gravatar.com/avatar.php?gravatar_id=".md5($seed)."&amp;&;size=$size&amp;default=$filename";
			if ($img){
				//f: crea immagine
				$filename='<img class="randavatarx" src="'.$filename.'" alt="'.str_replace('"',"'",$altImgText).' Randavatar" height="'.$displaySize.'" width="'.$displaySize.'"/>';
			}
			return $filename;
		} else { //php GD image manipulation is required
			return false; //php GD image isn't installed or file isn't writeable but don't want to mess up blog layout
		}
	}

	function image_colorize(&$im,$hue=1,$saturation=1,$part=''){
		$imgw = imagesx($im);
		$imgh = imagesy($im);
		/*//DOESN'T PRESERVE ALPHA SO DOESN'T WORK
		imagetruecolortopalette($im,true,1000);
		$numColors=imagecolorstotal($im);
		for($i=0;$i<$numColors;$i++){
			$color=imagecolorsforindex($im,$i);
			$lightness=($color['red']+$color['green']+$color['blue'])/3/255;
			var_dump($color);
			if($color['alpha']!=0){
				var_dump("|||||||||||||||||||||||");
			}
			if($lightness>.1&&$lightness<.99&&$color['alpha']<115){
				$newrgb=$this->HSL2hex(array($hue,$saturation,$lightness));
				imagecolorset ($im, $i, $newrgb[0],$newrgb[1],$newrgb[2]);
			}
		}*/
		imagealphablending($im,FALSE);
		if($optimize=$this->partOptimization[$part]){
			$xmin=$optimize[0][0];
			$xmax=$optimize[0][1];
			$ymin=$optimize[1][0];
			$ymax=$optimize[1][1];
		}else{
			$xmin=0;
			$xmax=$imgw-1;
			$ymin=0;
			$ymax=$imgh-1;
		}
		for($i=$xmin;$i<=$xmax;$i++){
			for($j=$ymin;$j<=$ymax;$j++){
				$rgb=ImageColorAt($im, $i, $j);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				$alpha = ($rgb & 0x7F000000) >> 24;
				$lightness=($r+$g+$b)/3/255;
				if($lightness>.1&&$lightness<.99&&$alpha<115){
					$newrgb=$this->HSL2hex(array($hue,$saturation,$lightness));
					$color=imagecolorallocatealpha($im, $newrgb[0],$newrgb[1],$newrgb[2],$alpha);
					imagesetpixel($im,$i,$j,$color);
				}
			}
		}
		imagealphablending($im,TRUE);
		return($im);
	}

	function image_whitize(&$im){
		$imgw = imagesx($im);
		$imgh = imagesy($im);
		imagealphablending($im,FALSE);
		for($i=0; $i<$imgh; $i++) {
			for($j=0; $j<$imgw; $j++) {
				$rgb=ImageColorAt($im, $i, $j);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				$alpha = ($rgb & 0x7F000000) >> 24;
				$lightness=($r+$g+$b)/3/255;
				if($lightness<=.1&&$alpha<115){
					$newrgb=$this->HSL2hex(array(0,0,1-$lightness));
					$color=imagecolorallocatealpha($im, $newrgb[0],$newrgb[1],$newrgb[2],$alpha);
					imagesetpixel($im,$i,$j,$color);
				}
			}
		}
		imagealphablending($im,TRUE);
		imageSaveAlpha($im,true);
		return($im);
	}

	function HSL2hex($hsl){
		$hue=$hsl[0];
		$saturation=$hsl[1];
		$lightness=$hsl[2];
		if ($saturation == 0){
			$red = $lightness * 255;
			$green = $lightness * 255;
			$blue = $lightness * 255;
		} else {
			if ($lightness < 0.5) $var_2 = $lightness * (1 + $saturation);
			else $var_2 = ($lightness + $saturation) - ($saturation * $lightness);

			$var_1 = 2 * $lightness - $var_2;
			$red = 255 * $this->hue_2_rgb($var_1,$var_2,$hue + (1 / 3));
			$green = 255 * $this->hue_2_rgb($var_1,$var_2,$hue - (1 / 3));
			$blue = 255 * $this->hue_2_rgb($var_1,$var_2,$hue);
		}
		return array($red,$green,$blue);
	}

	function hue_2_rgb($v1,$v2,$vh){
		if ($vh < 0) $vh += 1;
		elseif ($vh > 1) $vh -= 1;
		if ((6 * $vh) < 1) $output=$v1 + ($v2 - $v1) * 6 * $vh;
		elseif ((2 * $vh) < 1) $output=$v2;
		elseif ((3 * $vh) < 2) $output=$v1 + ($v2 - $v1) * ((2 / 3 - $vh) * 6);
		else $output=$v1;
		return($output);
	}

}

#Create a randavatarx for later use
global $randavatarx;
$randavatarx=new randavatarx();



function randavatarx_subpanel() {
	global $randavatarx;
	echo "<div class='wrap'>";
	if (isset($_POST['submit'])) { //update the randavatar size option
		$randavatarX_options=$randavatarx->get_options();
		$randavatarsize=intval($_POST['randavatarsize']);
		if ($randavatarsize > 0 & $randavatarsize < 400){
			$randavatarX_options['size']=$randavatarsize;
		}else{
			echo "<div class='error'><p>Please enter an integer for size. Preferably between 30-200.</p></div>";		
		}
		foreach(array('backr','backg','backb') as $color){//update background color options
			$colorarray=explode('-',$_POST[$color]);
			if (count($colorarray)==1){
				$colorarray[1]=$colorarray[0];
			}
			$colorarray[0]=intval($colorarray[0]);
			$colorarray[1]=intval($colorarray[1]);
			if ($colorarray[0]>=0 & $colorarray[0]<256 & $colorarray[1]>=0 & $colorarray[1]<256){
				$randavatarX_options[$color]=$colorarray;
			}else{
				echo "<div class='error'><p>Please enter a range between two integers for the background color (e.g. 230-255) between 1 and 255. For a single color please enter a single value (e.g. white = 255 for r,g and b).</p></div>";		
			}
		}
		//Not using else on the odd chance some weird input gets sent
		if ($_POST['sfondo'] == 0) $randavatarX_options['sfondo']=0;
		elseif ($_POST['sfondo'] == 1){
			if(is_writable(WP_RANDAVATARPARTS_DIR)||$randavatarX_options['artistic'])
				$randavatarX_options['sfondo']=1;
			else{
				echo "<div class='error'>Directory ".WP_RANDAVATARPARTS_DIR." must be <a href='http://codex.wordpress.org/Changing_File_Permissions'>writeable</a>.</div>";
				$randavatarX_options['sfondo']=0;
			}
		}
		if ($_POST['autoadd'] == 0) $randavatarX_options['autoadd']=0;
		//f: modificato per escludere auto precedente a wp2.5
		elseif ($_POST['autoadd'] == 1) $randavatarX_options['autoadd']=2;
		elseif ($_POST['autoadd'] == 2) $randavatarX_options['autoadd']=2;
		if ($_POST['gravatar'] == 0) $randavatarX_options['gravatar']=0;
		elseif ($_POST['gravatar'] == 1) $randavatarX_options['gravatar']=1;
		//f: modificato per escludere classico
		if ($_POST['artistic'] == 0) $randavatarX_options['artistic']=1;
		elseif ($_POST['artistic'] == 1) $randavatarX_options['artistic']=1;
		//f: modificato per escludere colorato
		if ($_POST['greyscale'] == 0) $randavatarX_options['greyscale']=0;
		elseif ($_POST['greyscale'] == 1) $randavatarX_options['greyscale']=0;
		update_option('randavatarX', $randavatarX_options);
		// f: messaggio salvataggio opzioni
		?>

<div class="updated">
  <p><strong>
    <?php _e("(⌒‿⌒) Settings saved. Delete cache to see your new settings. If you like this plugin, you consider making a small donation. ","randavatar");?>
    </strong><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=WFLPUGCW9EJVJ"><img src="<?php echo plugins_url("/images/gui/paypal-horizontal.png", __FILE__);?>" alt="Donate with PayPal" title="Donate with PayPal"></a></p>
</div>
<?php
		}
		
		
		
	elseif (isset($_POST['clear'])){ //clear the randavatarx cache
		$dir=WP_RANDAVATARX_DIR_INTERNAL;
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (is_file($dir.$file) and preg_match('/^.*\.png$/',$file)){
					unlink($dir.$file);
				}
			}
			closedir($dh);
			?>
<div class="updated">
  <p><strong>
    <?php _e("ლ(ಠ益ಠლ) The cache is now empty. What did you do?","randavatar");?>
    </strong></p>
</div>
<?php	

		}
	}elseif(isset($_POST['cssreset'])){//reset randavatarx css to default
		$randavatarX_options=$randavatarx->get_options();
		$randavatarX_options['css']=DEFAULT_RANDAVATARX_CSS;
		update_option('randavatarX', $randavatarX_options);
	}elseif(isset($_POST['csssubmit'])){
		$randavatarX_options=$randavatarx->get_options();
		$randavatarX_options['css']=$_POST['randavatarx_css'];
		update_option('randavatarX', $randavatarX_options);
	}
	$randavatarX_options=$randavatarx->get_options(TRUE);
	//count file
	$randavatarX_count=0;
	//f: numero di avatar generabili possibili - variabile: randavatarX_possible richiamata nelle impostazioni
	$randavatarX_possible = "142.272";
	//f.
	$dir=WP_RANDAVATARX_DIR_INTERNAL;
	if ($dh = opendir($dir)) {
		while (($file = readdir($dh)) !== false) {
			if (is_file($dir.$file) and preg_match('/^.*\.png$/',$file)){
				$randavatarX_count++;
			}
		}
	}
		//make sure white sfondo/arms exist
	$dir=WP_RANDAVATARPARTS_DIR;
	$changed="";
	if ($randavatarX_options['sfondo']&&is_writable(WP_RANDAVATARPARTS_DIR)&&!$randavatarX_options['artistic']&&$dh = opendir($dir)) {
		while (($file = readdir($dh)) !== false) {
			if (is_file($dir.$file) and preg_match('/^(oldarms|oldsfondo|oldbody|oldextra)_.*\.png$/',$file)){
				if (!file_exists($dir.'w'.$file)){
					$original=imagecreatefrompng($dir.$file);
					$x = imagesx($original);
					$y = imagesy($original);
					$white=imageColorAllocate($original,230,230,230);
					for($i=0; $i<$y; $i++) {
						for($j=0; $j<$x; $j++) {
							$pos = imagecolorat($original, $j, $i);
							if ($pos==0) imagesetpixel($original, $j, $i, $white);
						}
					}
					imageSaveAlpha($original,true);
					imagepng($original,$dir.'w'.$file);
					$changed.='w'.$file.' ';
				}
			}
		}
		closedir($dh);
		if ($changed) echo "<div class='updated'><p>White part files generated: $changed created.</p></div>";
	}
	?>
<div class=wrap>
<?php if(function_exists('screen_icon')) screen_icon(); ?>
<h2>
  <?php _e('FL3R Randavatar - Settings', 'randavatar'); ?>
</h2>
<link href="<?php echo plugins_url("css/style.css", __FILE__);?>" type="text/css" rel="stylesheet" />
<script src="<?php echo plugins_url("js/modernizr.js", __FILE__);?>"></script>
<div class="cd-tabs">
<nav>
  <ul class="cd-tabs-navigation">
    <li><a data-content="settings" class="selected" href="#0">
      <?php _e('Settings', 'randavatar'); ?>
      </a></li>
    <li><a data-content="faq" href="#0">
      <?php _e('FAQ', 'randavatar'); ?>
      </a></li>
    <li><a data-content="credits" href="#0">
      <?php _e('Credits', 'randavatar'); ?>
      </a></li>
    <li><a data-content="donate" href="#0">
      <?php _e('Donate', 'randavatar'); ?>
      </a></li>
    <li><a data-content="changelog" href="#0">
      <?php _e('Changelog', 'randavatar'); ?>
      </a></li>
    <li><a data-content="myplugins" href="#0">
      <?php _e('My plugins', 'randavatar'); ?>
      </a></li>
  </ul>
</nav>
<ul class="cd-tabs-content">
<li data-content="settings" class="selected">
  <link href="<?php echo plugins_url("css/style.css", __FILE__);?>" type="text/css" rel="stylesheet" />
  <script src="<?php echo plugins_url("js/modernizr.js", __FILE__);?>"></script> 
  <div class="cd-tabs">
    <nav>
      <ul class="cd-tabs-navigation">
        <li><a data-content="settings" class="selected" href="#0">
          <?php _e('Settings', 'randavatar'); ?>
          </a></li>
        <li><a data-content="faq" href="#0">
          <?php _e('FAQ', 'randavatar'); ?>
          </a></li>
        <li><a data-content="credits" href="#0">
          <?php _e('Credits', 'randavatar'); ?>
          </a></li>
        <li><a data-content="donate" href="#0">
          <?php _e('Donate', 'randavatar'); ?>
          </a></li>
        <li><a data-content="changelog" href="#0">
          <?php _e('Changelog', 'randavatar'); ?>
          </a></li>
        <li><a data-content="myplugins" href="#0">
          <?php _e('My plugins', 'randavatar'); ?>
          </a></li>
      </ul>
    </nav>
    <ul class="cd-tabs-content">
      <li data-content="settings" class="selected">
        <div class='wrap'>
          <h3>FL3R Randavatar</h3>
          <p> <img src="<?php echo plugins_url("/images/gui/fl3r-randavatar.png", __FILE__);?>" alt="<?php _e('FL3R Randavatar', 'randavatar'); ?>" title="<?php _e('FL3R Randavatar', 'randavatar'); ?>" class="fl3r-uac_icon_dashboard"><?php _e('You currently have', 'randavatar'); ?> <?php echo $randavatarX_count;?> <?php _e('randavatars cached on your website on', 'randavatar'); ?> <?php echo $randavatarX_possible;?> <?php _e('possible avatars!', 'randavatar'); ?></p>
          <form method="post" action="options-general.php?page=randavatar.php">
            <ul style="list-style-type: none">
              <p><strong><?php _e('Randavatars size', 'randavatar'); ?></strong> <?php _e('in pixels (default: 80)', 'randavatar'); ?><br/>
                <input type="text" name="randavatarsize" value="<?php echo $randavatarX_options['size'];?>"/>
              </p>
              <p> <strong><?php _e('Background color', 'randavatar'); ?></strong><?php _e(' - Enter single value or range (default, transparent: 0-0,0-0,0-0)', 'randavatar'); ?><br/>
                <?php _e('Red:', 'randavatar'); ?>
                <input type="text" name="backr" value="<?php echo implode($randavatarX_options['backr'],'-');?>"/>
                <?php _e('Green:', 'randavatar'); ?>
                <input type="text" name="backg" value="<?php echo implode($randavatarX_options['backg'],'-');?>"/>
                <?php _e('Blue:', 'randavatar'); ?>
                <input type="text" name="backb" value="<?php echo implode($randavatarX_options['backb'],'-');?>"/>
              </p>
              <!--<strong>Arm/Leg Color</strong> (change sfondo and arms to white if on dark background) (default: black)<br /> <input type="radio" name="sfondo" value="0" <?php if (!$randavatarX_options['sfondo']) echo 'checked="checked"';?>> Black <input type="radio" name="sfondo" value="1" <?php if ($randavatarX_options['sfondo']) echo 'checked="checked"';?>> White <br />(Please make sure the folder <code>wp-content/plugins/randavatarx/parts/</code> is writeable before changing to White)-->
              <p>
			  <strong><?php _e('Default randavatars', 'randavatar'); ?></strong><?php _e(' - Adds a randavatar automatically beside commenter names or disable it and edit theme file manually (default: Auto)', 'randavatar'); ?><br/>
                <input type="radio" name="autoadd" value="2" <?php if ($randavatarX_options['autoadd']==2) echo 'checked="checked"';?>/>
                <?php _e('Auto', 'randavatar'); ?>
                <input type="radio" name="autoadd" value="0" <?php if (!$randavatarX_options['autoadd']) echo 'checked="checked"';?>>
                <?php _e('Manual', 'randavatar'); ?><!--<input type="radio" name="autoadd" value="1" <?php if ($randavatarX_options['autoadd']==1) echo 'checked="checked"';?>> Auto (WP before 2.5) --> 
              </p>
              <strong><?php _e('Gravatar', 'randavatar'); ?></strong><?php _e(' - If a commenter has a Gravatar use it, otherwise use randavatars (default: Randavatar)', 'randavatar'); ?><br/>
              <input type="radio" name="gravatar" value="0" <?php if (!$randavatarX_options['gravatar']) echo 'checked="checked"';?>>
              <?php _e('Randavatar', 'randavatar'); ?>
              <input type="radio" name="gravatar" value="1" <?php if ($randavatarX_options['gravatar']) echo 'checked="checked"';?>>
              <?php _e('Randavatars + Gravatar', 'randavatar'); ?>
              </p>
              <!--<strong>Artistic</strong> (Artistic randavatars require more processing) (default: Artistic)<br /> <input type="radio" name="artistic" value="1" <?php if ($randavatarX_options['artistic']) echo 'checked="checked"';?>> Artistic <input type="radio" name="artistic" value="0" <?php if (!$randavatarX_options['artistic']) echo 'checked="checked"';?>> Original-->
              <?php if($randavatarX_options['artistic']){?>
              <!--<strong>Grey Scale Monsters</strong> (Greyscale artistic require less processing) (default: Color)<br /> <input type="radio" name="greyscale" value="1" <?php if ($randavatarX_options['greyscale']) echo 'checked="checked"';?>> Color <input type="radio" name="greyscale" value="0" <?php if (!$randavatarX_options['greyscale']) echo 'checked="checked"';?>> Greyscale -->
              <?php }?>
              <p>
                <input type="submit" name="submit" value="<?php _e('Save settings', 'randavatar'); ?>"/>
              </p>
            </ul>
          </form>
          <form method="post" action="options-general.php?page=randavatar.php">
            <p>
            <ul style="list-style-type: none">
              <input type="submit" name="clear" value="<?php _e('Clear cache', 'randavatar'); ?>"/>
            </ul>
            </p>
          </form>
          <p>
          <h3><?php _e('Custom CSS', 'randavatar'); ?></h3>
          <form method="post" action="options-general.php?page=randavatar.php">
            <ul style="list-style-type: none">
              <textarea name="randavatarx_css" rows="5" cols="70"><?php echo $randavatarX_options['css'];?></textarea>
              <br/>
              <p>
                <input type="submit" name="csssubmit" value="<?php _e('Save CSS', 'randavatar'); ?>"/>
                <input type="submit" name="cssreset" value="<?php _e('Reset CSS', 'randavatar'); ?>"/>
            </ul>
          </form>
          </p>
        </div>
      </li>
      <li data-content="faq">
        <h3>
          <?php _e('FAQ', 'randavatar'); ?>
        </h3>
        <div class='wrap'>
        <p><i>
          <?php _e('How can I show randavatars in a widget?', 'randavatar'); ?>
          </i></p>
        <p>
          <?php _e('You can use the FL3R Randavatar widget.', 'randavatar'); ?>
        </p>
        <br>
        <p><i>
          <?php _e('How can I manually insert randavatars in my theme?', 'randavatar'); ?>
          </i></p>
        <p>
          <?php _e('You can do this with ', 'randavatar'); ?>
          <code> <?php echo htmlspecialchars('<?php if (function_exists("randavatarx_build_randavatar")) {echo randavatarx_build_randavatar($comment->comment_author_email, $comment->comment_author); } ?>');?></code>
          <?php _e(' in your comments.php or if you want only the image URL you can use ', 'randavatar'); ?>
          <code><?php echo htmlspecialchars('<?php if (function_exists("randavatarx_build_randavatar")) {echo randavatarx_build_randavatar($comment->comment_author_email, $comment->comment_author,false); } ?>');?></code></p>
        <br>
        <p><i>
          <?php _e('I can not see randavatars. Why?', 'randavatar'); ?>
          </i></p>
        <p>
          <?php _e('Make sure sure the folder <code>wp-content/plugins/fl3r-randavatar</code> is <a href="http://codex.wordpress.org/Changing_File_Permissions">writeable</a>.', 'randavatar'); ?>
        </p>
        <br>
        <p><i>
          <?php _e('How can I know randavatars works properly?', 'randavatar'); ?>
          </i></p>
        <p>
          <?php _e('A random generated randavatar should be here. ', 'randavatar'); ?>
        </p>
        <p>
          <?php 
	  //f: crea numero random per avatar random nelle impostazioni
	  //echo randavatarx_build_randavatar('Test Randavatar','Test');
	  echo randavatarx_build_randavatar(rand(),rand());
	?>
        </p>
      </li>
      <li data-content="credits">
        <h3>
          <?php _e('Credits', 'randavatar'); ?>
        </h3>
        <p>
          <?php _e('Randavatar is a plugin created by Armando "FL3R" Fiore.', 'randavatar'); ?>
        </p>
        <p>
          <?php _e('Thanks for using Randavatar, you helped a developer to increase his self-esteem!', 'randavatar'); ?>
        </p>
        <h3>
          <?php _e('Copyright', 'randavatar'); ?>
        </h3>
        <p>
          <?php _e('Copyright © 2015 Armando "FL3R" Fiore. All rights reserved. This software is provided as is, without any express or implied warranty. In no event shall the author be liable for any damage arising from the use of this software.', 'randavatar'); ?>
        </p>
        <p>
          <?php _e('Portions of the code is based on WP_MonsterID plugin by scottsm.', 'randavatar'); ?>
        </p>
        <p>
          <?php _e('This plugin uses modified images from original works of Emoji One. The original Emoji One\'s emoji graphics are free to use for any project, commercial or personal under a "Free Culture" Creative Commons License (CC-BY-SA).', 'randavatar'); ?>
        </p>
        <h3>
          <?php _e('Follow me!', 'randavatar'); ?>
        </h3>
        <p>
          <?php _e('If you want to ask me, you want to send your opinion or you have a question please don\'t hesitate to contact me.', 'randavatar'); ?>
        </p>
        <p> <a href="https://twitter.com/Armando_Fiore"><img src="<?php echo plugins_url("/images/gui/twitter.png", __FILE__);?>" alt="Twitter: Armando_Fiore" title="Twitter: Armando_Fiore" class="fl3r-uac_icon_dashboard"></a> <a href="https://www.facebook.com/armando.FL3R.fiore"><img src="<?php echo plugins_url("/images/gui/facebook.png", __FILE__);?>" alt="Facebook: armando.FL3R.fiore" title="Facebook: armando.FL3R.fiore" class="fl3r-uac_icon_dashboard"></a> <a href="https://plus.google.com/+ArmandoFiore"><img src="<?php echo plugins_url("/images/gui/google.png", __FILE__);?>" alt="Google+: ArmandoFiore" title="Google+: ArmandoFiore" class="fl3r-uac_icon_dashboard"></a> <a href="http://it.linkedin.com/in/armandofiore"><img src="<?php echo plugins_url("/images/gui/linkedin.png", __FILE__);?>" alt="LinkedIn: armandofiore" title="LinkedIn: armandofiore" class="fl3r-uac_icon_dashboard"></a> </p>
      </li>
      <li data-content="donate">
        <div>
          <h3>
            <?php _e('Donate', 'randavatar'); ?>
          </h3>
          <p>
            <?php _e('If you like this plugin, you consider making a small donation. Thanks.', 'randavatar'); ?>
          </p>
          <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=WFLPUGCW9EJVJ"><img src="<?php echo plugins_url("/images/gui/paypal-horizontal.png", __FILE__);?>" alt="Donate with PayPal" title="Donate with PayPal" class="fl3r-donate-message"></a> </div>
      </li>
      <li data-content="changelog">
        <div>
          <h3>
            <?php _e('Changelog', 'randavatar'); ?>
          </h3>
          <b>1.0</b>
          <p>
            <?php _e('First release. 142.272 avatars can be created.', 'randavatar'); ?>
          </p>
        </div>
      </li>
      <li data-content="myplugins">
        <div>
          <h3>
            <?php _e('My plugins', 'randavatar'); ?>
          </h3>
          <img src="<?php echo plugins_url("/images/gui/fl3r-feelbox.png", __FILE__);?>" alt="<?php _e('FL3R FeelBox', 'randavatar'); ?>" title="<?php _e('FL3R FeelBox', 'randavatar'); ?>" class="fl3r-uac_icon_dashboard"><a href="https://wordpress.org/plugins/fl3r-feelbox/"><b>
          <?php _e('FL3R FeelBox', 'randavatar'); ?>
          </a> </b>
          <?php _e('adds an one-click real-time mood rating FeelBox to all of your posts. Oh, there is also a widget.', 'randavatar'); ?>
          </p>
          <img src="<?php echo plugins_url("/images/gui/fl3r-user-agent-comments.png", __FILE__);?>" alt="<?php _e('FL3R User Agent Comments', 'randavatar'); ?>" title="<?php _e('FL3R User Agent Comments', 'randavatar'); ?>" class="fl3r-uac_icon_dashboard"><a href="https://wordpress.org/plugins/fl3r-user-agent-comments/"><b>
          <?php _e('FL3R User Agent Comments', 'randavatar'); ?>
          </a> </b>
          <?php _e('show the browser and the operating system of your users in the comments and create a chain of comments most beautiful and interesting to read!', 'randavatar'); ?>
          </p>
          <img src="<?php echo plugins_url("/images/gui/fl3r-randavatar.png", __FILE__);?>" alt="<?php _e('FL3R Randavatar', 'randavatar'); ?>" title="<?php _e('FL3R Randavatar', 'randavatar'); ?>" class="fl3r-uac_icon_dashboard"><a href="https://wordpress.org/plugins/fl3r-randavatar/"><b>
          <?php _e('FL3R Randavatar', 'randavatar'); ?>
          </a> </b>
          <?php _e('provides a large randomly assembled avatar for each user based on their email address. When a user comments on the site, the plugin generates a unique avatar based on his email. There are an impressive number of avatars that can be generated, and it increases at each update of the plugin! ', 'randavatar'); ?>
        </div>
      </li>
    </ul>
  </div>
  <?php wp_enqueue_script('jquery'); ?>
  <script src="<?php echo plugins_url("js/main.js", __FILE__);?>"></script>
  <?php	
}


function randavatarx_build_randavatar($seed='',$altImgText='',$img=true,$size='',$write=true,$displaySize='',$gravataron=true){
	global $randavatarx;
	if (!isset($randavatarx))$randavatarx=new randavatarx();
	if(isset($randavatarx)){
		return $randavatarx->build_randavatar($seed,$altImgText,$img,$size,$write,$displaySize,$gravataron);
	}else return false;
}

function randavatarx_comment_author($output){
	global $comment;
	global $randavatarx;
	if(!isset($randavatarx)) return $output;
	$randavatarx_options=$randavatarx->get_options();
	if((is_page () || is_single ()) && $randavatarx_options['autoadd']==1 && $comment->comment_type!="pingback" && $comment->comment_type!="trackback" &&  isset($comment->comment_karma)) //assuming sidebar widgets won't check comment karma (and single page comments will))
	  $output=randavatarx_build_randavatar($comment->comment_author_email,$comment->comment_author).' '.$output; 
	return $output;
}

function randavatarx_get_avatar($avatar, $id_or_email, $size, $default){
	global $randavatarx;
	if(!isset($randavatarx)) return $avatar;
	$email = '';
	if ( is_numeric($id_or_email) ) {
		$id = (int) $id_or_email;
		$user = get_userdata($id);
		if ( $user )
			$email = $user->user_email;
	} elseif ( is_object($id_or_email) ) {
		if ( !empty($id_or_email->user_id) ) {
			$id = (int) $id_or_email->user_id;
			$user = get_userdata($id);
			if ( $user)
				$email = $user->user_email;
		} elseif ( !empty($id_or_email->comment_author_email) ) {
			$email = $id_or_email->comment_author_email;
		}
	} else {
		$email = $id_or_email;
	}

	if(!$avatar) return randavatarx_build($email,'','',true,$size);
	if(!$randavatarx->randavatarx_options['gravatar']){
		$randavatarxurl=randavatarx_build_randavatar($email,'',false);
		$newavatar=preg_replace('@src=(["\'])http://[^"\']+["\']@','src=\1'.$randavatarxurl.'\1',$avatar);
		$avatar=$newavatar;
	}elseif($randavatarx->randavatarx_options['gravatar']==1){
		$randavatarxurl=randavatarx_build_randavatar($email,'',false,'',true,$size,false);
		if(strpos($avatar,'default=http://')!==false){
			$newavatar=preg_replace('@default=http://[^&\'"]+([&\'"])@','default='.urlencode($randavatarxurl).'\1',$avatar);
		}else{
			$newavatar=preg_replace('@(src=(["\'])http://[^?]+\?)@','\1default='.urlencode($randavatarxurl).'&amp;',$avatar);
		}
		$avatar=$newavatar;
	}
	return($avatar);
}



function randavatarx_style() {
	global $randavatarx;
	$options = $randavatarx->get_options();
	if($css = $options['css']){
?>
  <style type="text/css">
<?php echo $css; ?>
</style>
  <?php
	}
}

//Hooks
add_action('admin_menu', 'randavatarx_menu');
add_filter('get_comment_author','randavatarx_comment_author');
add_action('wp_head', 'randavatarx_style');
if($wp_version>=2.5&&$randavatarx->randavatarx_options['autoadd']==2){
	add_filter('get_avatar','randavatarx_get_avatar',5,4);
}

class mid_mersenne_twister{
	//MySQL version doesn't work since they shut down integer overflow switching to:
	//https://github.com/ruafozy/php-mersenne-twister/blob/master/src/mersenne_twister.php

	function mid_mersenne_twister($seed=123456) {
		$this->bits32 = PHP_INT_MAX == 2147483647;
		$this->define_constants();
		$this->init_with_integer($seed);
	}

	function define_constants() {
		$this->N = 624;
		$this->M = 397;
		$this->MATRIX_A = 0x9908b0df;
		$this->UPPER_MASK = 0x80000000;
		$this->LOWER_MASK = 0x7fffffff;

		$this->MASK10=~((~0) << 10); 
		$this->MASK11=~((~0) << 11); 
		$this->MASK12=~((~0) << 12); 
		$this->MASK14=~((~0) << 14); 
		$this->MASK20=~((~0) << 20); 
		$this->MASK21=~((~0) << 21); 
		$this->MASK22=~((~0) << 22); 
		$this->MASK26=~((~0) << 26); 
		$this->MASK27=~((~0) << 27); 
		$this->MASK31=~((~0) << 31); 

		$this->TWO_TO_THE_16=pow(2,16);
		$this->TWO_TO_THE_31=pow(2,31);
		$this->TWO_TO_THE_32=pow(2,32);

		$this->MASK32 = $this->MASK31 | ($this->MASK31 << 1);
	}

	function init_with_integer($integer_seed) {
		$integer_seed = $this->force_32_bit_int($integer_seed);

		$mt = &$this->mt;
		$mti = &$this->mti;

		$mt = array_fill(0, $this->N, 0);

		$mt[0] = $integer_seed;

		for($mti = 1; $mti < $this->N; $mti++) {
			$mt[$mti] = $this->add_2($this->mul(1812433253,
				($mt[$mti - 1] ^ (($mt[$mti - 1] >> 30) & 3))), $mti);
		/*
		mt[mti] =
			 (1812433253UL * (mt[mti-1] ^ (mt[mti-1] >> 30)) + mti);
		 */
		}
	}

	/* generates a random number on [0,1)-real-interval */
	function real_halfopen() {
		return
			$this->signed2unsigned($this->int32()) * (1.0 / 4294967296.0);
	}
	function int32() {
		$mag01 = array(0, $this->MATRIX_A);

		$mt = &$this->mt;
		$mti = &$this->mti;

		if ($mti >= $this->N) { /* generate N words all at once */
			for ($kk=0;$kk<$this->N-$this->M;$kk++) {
				$y = ($mt[$kk]&$this->UPPER_MASK)|($mt[$kk+1]&$this->LOWER_MASK);
				$mt[$kk] = $mt[$kk+$this->M] ^ (($y >> 1) & $this->MASK31) ^ $mag01[$y & 1];
			}
			for (;$kk<$this->N-1;$kk++) {
				$y = ($mt[$kk]&$this->UPPER_MASK)|($mt[$kk+1]&$this->LOWER_MASK);
				$mt[$kk] =
					$mt[$kk+($this->M-$this->N)] ^ (($y >> 1) & $this->MASK31) ^ $mag01[$y & 1];
			}
			$y = ($mt[$this->N-1]&$this->UPPER_MASK)|($mt[0]&$this->LOWER_MASK);
			$mt[$this->N-1] = $mt[$this->M-1] ^ (($y >> 1) & $this->MASK31) ^ $mag01[$y & 1];

			$mti = 0;
		}

		$y = $mt[$mti++];

		/* Tempering */
		$y ^= ($y >> 11) & $this->MASK21;
		$y ^= ($y << 7) & ((0x9d2c << 16) | 0x5680);
		$y ^= ($y << 15) & (0xefc6 << 16);
		$y ^= ($y >> 18) & $this->MASK14;

		return $y;
	}

	function signed2unsigned($signed_integer) {
		## assert(is_integer($signed_integer));
		## assert(($signed_integer & ~$this->MASK32) === 0);

		return $signed_integer >= 0? $signed_integer:
			$this->TWO_TO_THE_32 + $signed_integer;
	}

	function unsigned2signed($unsigned_integer) {
		## assert($unsigned_integer >= 0);
		## assert($unsigned_integer < pow(2, 32));
		## assert(floor($unsigned_integer) === floatval($unsigned_integer));

		return intval($unsigned_integer < $this->TWO_TO_THE_31? $unsigned_integer:
			$unsigned_integer - $this->TWO_TO_THE_32);
	}

	function force_32_bit_int($x) {
  /*
	 it would be un-PHP-like to require is_integer($x),
	 so we have to handle cases like this:

		$x === pow(2, 31)
		$x === strval(pow(2, 31))

	 we are also opting to do something sensible (rather than dying)
	 if the seed is outside the range of a 32-bit unsigned integer.
	*/

		if(is_integer($x)) {
	 /* 
		we mask in case we are on a 64-bit machine and at least one
		bit is set between position 32 and position 63.
	  */
			return $x & $this->MASK32;
		} else {
			$x = floatval($x);

			$x = $x < 0? ceil($x): floor($x);

			$x = fmod($x, $this->TWO_TO_THE_32);

			if($x < 0)
				$x += $this->TWO_TO_THE_32;

			return $this->unsigned2signed($x);
		}
	}

  /*
	takes 2 integers, treats them as unsigned 32-bit integers,
	and adds them.

	it works by splitting each integer into
	2 "half-integers", then adding the high and low half-integers
	separately.

	a slight complication is that the sum of the low half-integers
	may not fit into 16 bits; any "overspill" is added to the sum
	of the high half-integers.
	*/ 
	function add_2($n1, $n2) {
		$x = ($n1 & 0xffff) + ($n2 & 0xffff);

		return 
			(((($n1 >> 16) & 0xffff) + 
			(($n2 >> 16) & 0xffff) + 
			($x >> 16)) << 16) | ($x & 0xffff);
	}

	function mul($a, $b) {
  /*
	 a and b, considered as unsigned integers, can be expressed as follows:

		a = 2**16 * a1 + a2,

		b = 2**16 * b1 + b2,

		where

	0 <= a2 < 2**16,
	0 <= b2 < 2**16.

	 given those 2 equations, what this function essentially does is to
	 use the following identity:

		a * b = 2**32 * a1 * b1 + 2**16 * a1 * b2 + 2**16 * b1 * a2 + a2 * b2

	 note that the first term, i.e. 2**32 * a1 * b1, is unnecessary here,
	 so we don't compute it.

	 we could make the following code clearer by using intermediate
	 variables, but that would probably hurt performance.
	*/

		return
			$this->unsigned2signed(
				fmod(
					$this->TWO_TO_THE_16 *
	  /*
		 the next line of code calculates a1 * b2,
		 the line after that calculates b1 * a2, 
		 and the line after that calculates a2 * b2.
		*/
					((($a >> 16) & 0xffff) * ($b & 0xffff) +
					(($b >> 16) & 0xffff) * ($a & 0xffff)) +
					($a & 0xffff) * ($b & 0xffff),

					$this->TWO_TO_THE_32));
	}

	function rand($low,$high){
		$pick=floor($low+($high-$low+1)*$this->real_halfopen());
		return ($pick);
	}

	function array_rand($array){
		return($this->rand(0,count($array)-1));
	}
}


//Widget stuff 
//Wordpress's default widget doesn't get commenter email so we can't use it for randavatarxs
//Copying their widget with some search and replace with randavatarx
function randavatarx_recent_comments($args) {
	global $wpdb, $comments, $comment, $randavatarx;
	extract($args, EXTR_SKIP);
	$options = get_option('widget_randavatarx_recent_comments');
	$title = empty($options['title']) ? __('Recent Comments') : $options['title'];
	if ( !$number = (int) $options['number'] )
		$number = 5;
	else if ( $number < 1 )
		$number = 1;
	else if ( $number > 15 )
		$number = 15;
	if ( !$size = (int) $options['randavatarx_size'] )
		$size = 30;
	else if ( $size < 5 )
		$size=5;
	else if($size > 80)
		$size=80;
	if ( !$comments = wp_cache_get( 'randavatarx_recent_comments', 'widget' ) ) {
		$comments = $wpdb->get_results("SELECT comment_author, comment_author_url, comment_ID, comment_post_ID, comment_author_email, comment_type FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT $number");
		wp_cache_add( 'randavatarx_recent_comments', $comments, 'widget' );
	}
?>
  <?php echo $before_widget; ?> <?php echo $before_title . $title . $after_title; ?>
  <ul id="randavatarx_recentcomments">
    <?php
			if ( $comments ) : foreach ($comments as $comment) :
				echo  '<li class="recentcomments">';
				if($comment->comment_type!="pingback"&&$comment->comment_type!="trackback")
					echo randavatarx_build_randavatar($comment->comment_author_email,$comment->comment_author,TRUE,'',TRUE,$size);
				echo sprintf(__('%1$s on %2$s'), get_comment_author_link(), '<a href="'. get_permalink($comment->comment_post_ID) . '#comment-' . $comment->comment_ID . '">' . get_the_title($comment->comment_post_ID) . '</a>') . '</li>';
			endforeach; endif;?>
  </ul>
  <?php echo $after_widget; ?>
  <?php
}

function wp_delete_randavatarx_recent_comments_cache() {
	wp_cache_delete( 'randavatarx_recent_comments', 'widget' );
}

function randavatarx_recent_comments_control() {
	$options = $newoptions = get_option('widget_randavatarx_recent_comments');
	if ( $_POST["randavatarx_recent-comments-submit"] ) {
		$newoptions['title'] = strip_tags(stripslashes($_POST["randavatarx_recent-comments-title"]));
		$newoptions['number'] = (int) $_POST["randavatarx_recent-comments-number"];
		$newoptions['randavatarx_size'] = (int) $_POST["randavatarx_size"];
		$newoptions['randavatarx_css'] =  $_POST["randavatarx_css"];
	}
	if($_POST["randavatarx_css_reset"])
		$newoptions['randavatarx_css'] = DEFAULT_RANDAVATARX_RECENTCOMMENTS_CSS;
	if ( $options != $newoptions ) {
		$options = $newoptions;
		update_option('widget_randavatarx_recent_comments', $options);
		wp_delete_randavatarx_recent_comments_cache();
	}
	$title = attribute_escape($options['title']);
	if ( !$number = (int) $options['number'] )
		$number = 5;
	if ( !$size = (int) $options['randavatarx_size'] )
		$size = 25;
	if(!$css = stripslashes($options['randavatarx_css']))
		$css = DEFAULT_RANDAVATARX_RECENTCOMMENTS_CSS;
?>
  <p>
    <label for="randavatarx_recent-comments-title">
      <?php _e('Title:'); ?>
      <input style="width: 250px;" id="randavatarx_recent-comments-title" name="randavatarx_recent-comments-title" type="text" value="<?php echo $title; ?>" />
    </label>
  </p>
  <p>
    <label for="randavatarx_recent-comments-number">
      <?php _e('Number of comments to show:'); ?>
      <input style="width: 25px; text-align: center;" id="randavatarx_recent-comments-number" name="randavatarx_recent-comments-number" type="text" value="<?php echo $number; ?>" />
    </label>
    <?php _e('(at most 15)'); ?>
  </p>
  <p>
    <label for="randavatarx_size">
      <?php _e('Size of widget Randavatar (pixels):'); ?>
      <input style="width: 25px; text-align: center;" id="randavatarx_size" name="randavatarx_size" type="text" value="<?php echo $size; ?>" />
    </label>
  </p>
  <p>
    <label for="randavatarx_css">
      <?php _e('CSS for widget:'); ?>
      <textarea id="randavatarx_css" name="randavatarx_css" rows="3" cols="55" />
      <?php echo $css;?>
      </textarea>
    </label>
  </p>
  <p>
    <label for="randavatarx_css_reset">
      <?php _e('Reset CSS to Default:'); ?>
      <input id="randavatarx_css_reset" name="randavatarx_css_reset" type="submit" value="Reset CSS" />
    </label>
  </p>
  <input type="hidden" id="randavatarx_recent-comments-submit" name="randavatarx_recent-comments-submit" value="1" />
  <?php
}

function randavatarx_recent_comments_style() {
	$options = get_option('widget_randavatarx_recent_comments');
	if(!$css = stripslashes($options['randavatarx_css']))
		$css = DEFAULT_RANDAVATARX_RECENTCOMMENTS_CSS;
?>
  <style type="text/css">
<?php echo $css; ?>
</style>
  <?php
}

function randavatarx_recent_comments_widget_init(){
	register_sidebar_widget('Recent Comments (with RANDAVATARXs)', 'randavatarx_recent_comments');
	register_widget_control('Recent Comments (with RANDAVATARXs)', 'randavatarx_recent_comments_control', 400, 250);
	if ( is_active_widget('randavatarx_recent_comments') )
		add_action('wp_head', 'randavatarx_recent_comments_style');
	add_action( 'comment_post', 'wp_delete_randavatarx_recent_comments_cache' );
	add_action( 'wp_set_comment_status', 'wp_delete_randavatarx_recent_comments_cache' );
}

add_action('widgets_init', 'randavatarx_recent_comments_widget_init');

?>
