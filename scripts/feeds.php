<?php if (!defined('PmWiki')) exit();
/*  Copyright 2005 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    References:
      http://dublincore.org/documents/dcmes-xml/
      http://www.atomenabled.org/developers/syndication/
*/


## Settings for ?action=atom
SDVA($FeedFmt['atom']['feed'], array(
  '_start' => '<?xml version="1.0" encoding="$Charset"?'.'>
<!DOCTYPE rdf:RDF PUBLIC "-//DUBLIN CORE//DCMES DTD 2002/07/31//EN"
    "http://dublincore.org/documents/2002/07/31/dcmes-xml/dcmes-xml-dtd.dtd">
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:dc="http://purl.org/dc/elements/1.1/">'."\n",
  '_end' => "</rdf:RDF>\n",
  'title' => '$WikiTitle',
  'id' => '$PageUrl?action=atom',
  'updated' => '$FeedISOTime',
  'author' => "<author><name>Patrick R. Michaud</name></author>\n",
  'generator' => '$Version',
  'logo' => '$PageLogoUrl'));
SDVA($FeedFmt['atom']['item'], array(
  '_start' => "<entry>\n",
  'id' => '$PageUrl',
  'title' => '$Title',
  'updated' => '$ItemISOTime',
  'link' => '<link rel="alternate" href="$PageUrl" />',
  'author' => '<author>$LastModifiedBy</author>',
  'summary' => '$ItemDesc',
  'category' => '<category term="$Category" />',
  '_end' => "</entry>\n"));

## Settings for ?action=dc
SDVA($FeedFmt['dc']['feed'], array(
  '_start' => '<?xml version="1.0" encoding="$Charset"?'.'>
<!DOCTYPE rdf:RDF PUBLIC "-//DUBLIN CORE//DCMES DTD 2002/07/31//EN"
    "http://dublincore.org/documents/2002/07/31/dcmes-xml/dcmes-xml-dtd.dtd">
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:dc="http://purl.org/dc/elements/1.1/">'."\n",
  '_end' => "</rdf:RDF>\n"));
SDVA($FeedFmt['dc']['item'], array(
  '_start' => "<rdf:Description rdf:about=\"\$PageUrl\">\n",
  'dc:title' => '$Title',
  'dc:identifier' => '$PageUrl',
  'dc:date' => '$ItemISOTime',
  'dc:type' => 'Text',
  'dc:format' => 'text/html',
  'dc:description' => '$ItemDesc',
  'dc:subject' => "<dc:subject>\$Category</dc:subject>\n",
  'dc:publisher' => '$WikiTitle',
  'dc:author' => '$LastModifiedBy',
  '_end' => "</rdf:Description>\n"));

## RSS 2.0 settings for ?action=rss
SDVA($FeedFmt['rss']['feed'], array(
  '_start' => '<?xml version="1.0" encoding="$Charset"?'.'>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>'."\n",
  '_end' => "</channel>\n</rss>\n",
  'title' => '$WikiTitle | $Group / $Title',
  'link' => '$PageUrl?action=rss',
  'description' => '$Group.$Title',
  'lastBuildDate' => '$FeedRSSTime'));
SDVA($FeedFmt['rss']['item'], array(
  '_start' => "<item>\n",
  '_end' => "</item>\n",
  'title' => '$Group / $Title',
  'link' => '$PageUrl',
  'description' => '$ItemDesc',
  'dc:contributor' => '$LastModifiedBy',
  'dc:date' => '$ItemISOTime',
  'enclosure' => 'RSSEnclosure'));

## RDF 1.0, for ?action=rdf
SDVA($FeedFmt['rdf']['feed'], array(
  '_start' => '<?xml version="1.0" encoding="$Charset"?'.'>
<rdf:RDF xmlns="http://purl.org/rss/1.0/"
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel rdf:about="$PageUrl?action=rdf">'."\n",
  'title' => '$WikiTitle | $Group / $Title',
  'link' => '$PageUrl?action=rdf',
  'description' => '$Group.$Title',
  'dc:date' => '$FeedISOTime',
  '_items' => "<rdf:Seq>\n<items>\n",
  '_end' => "</items>\n</rdf:Seq>\n</channel>\n</rdf:RDF>\n"));
SDVA($FeedFmt['rdf']['item'], array(
  '_start' => "<item rdf:about=\"\$PageUrl\">\n",
  '_end' => "</item>\n",
  'title' => '$WikiTitle | $Group / $Title',
  'link' => '$PageUrl',
  'description' => '$ItemDesc',
  'dc:date' => '$ItemISOTime'));
  
foreach(array_keys($FeedFmt) as $k) {
  SDV($HandleActions[$k], 'HandleFeed');
  SDV($HandleAuth[$k], 'read');
}

function HandleFeed($pagename, $auth = 'read') {
  global $FeedFmt, $action, $PCache, $FmtV, $ISOTimeFmt, $RSSTimeFmt,
    $FeedOpt, $FeedDescPatterns, $CategoryGroup, $EntitiesTable;
  SDV($ISOTimeFmt, '%Y-%m-%dT%H:%M:%SZ');
  SDV($RSSTimeFmt, 'D, d M Y H:i:s \G\M\T');
  SDVA($FeedOpt, array('count' => 20, 'trail' => $pagename, 'readf' => 1));
  SDV($FeedDescPatterns, array('/^.*<\\/\\w+>/s' => '$0', '/<[^>]+>/' => ''));

  $f = $FeedFmt[$action];
  $page = RetrieveAuthPage($pagename, $auth, true, READPAGE_CURRENT);
  if (!$page) Abort("?cannot generate feed");
  $feedtime = $page['time'];

  # determine list of pages to display
  if ($action=='dc') unset($FeedOpt['trail']);
  $opt = array_merge($FeedOpt, @$_REQUEST);
  if ($opt['trail'] || $opt['group'] || $opt['link']) 
    $pagelist = MakePageList($pagename, $opt);
  if (!@$pagelist) 
    { PCache($pagename, $page); $pagelist = array(&$PCache[$pagename]); }
  if (@$opt['count']) array_splice($pagelist, $opt['count']);

  # process list of pages in feed
  foreach($pagelist as $page) {
    $pn = $page['name'];
    #$page = PageMetadata($pn, ReadPage($pn, READPAGE_CURRENT));
    $pl[] = $page;
    if ($page['time'] > $feedtime) $feedtime = $page['time'];
  }
  $pagelist = $pl;

  $FmtV['$FeedISOTime'] = gmstrftime($ISOTimeFmt, $feedtime);
  $FmtV['$FeedRSSTime'] = gmdate($RSSTimeFmt, $feedtime);
  # format start of feed
  $out = FmtPageName($f['feed']['_start'], $pagename);

  # format feed elements
  foreach($f['feed'] as $k => $v) {
    if ($k{0} == '_') continue;
    $x = FmtPageName($v, $pagename);
    if (!$x) continue;
    $out .= ($v{0} == '<') ? $x : "<$k>$x</$k>\n";
  }

  # format items in feed
  if (@$f['feed']['_items']) 
    $out .= FmtPageName($f['feed']['_items'], $pagename);
  foreach($pagelist as $page) {
    $pn = $page['name'];
    $FmtV['$ItemDesc'] = (@$page['description']) 
      ? $page['description']
      : preg_replace(array_keys($FeedDescPatterns), 
                     array_values($FeedDescPatterns), @$page['excerpt']);
    $FmtV['$ItemISOTime'] = gmstrftime($ISOTimeFmt, $page['time']);

    $out .= FmtPageName($f['item']['_start'], $pn);
    foreach((array)@$f['item'] as $k => $v) {
      if ($k{0} == '_') continue;
      if (is_callable($v)) { $out .= $v($pn, $page, $k); continue; }
      if (strpos($v, '$Category') 
          && preg_match_all("/(?<=^|,)$CategoryGroup\\.([^,]+)/", 
                            $page['targets'], $match)) {
        foreach($match[1] as $c) {
          $FmtV['$Category'] = $c;
          $out .= FmtPageName($v, $pn);
        }
        continue;
      }
      $x = FmtPageName($v, $pn);
      if (!$x) continue;
      $out .= ($v{0} == '<') ? $x : "<$k>$x</$k>\n";
    }
    $out .= FmtPageName($f['item']['_end'], $pn);
  } 
  $out .= FmtPageName($f['feed']['_end'], $pagename);
  header('Content-type: text/xml');
  print str_replace(array_keys($EntitiesTable),
                    array_values($EntitiesTable), $out);
}

function RSSEnclosure($pagename, &$page, $k) {
  global $RSSEnclosureFmt, $UploadFileFmt, $UploadExts;
  if (!function_exists('MakeUploadName')) return '';
  SDV($RSSEnclosureFmt, array('$Name.mp3'));
  foreach((array)$RSSEnclosureFmt as $fmt) {
    $path = FmtPageName($fmt, $pagename);
    $upname = MakeUploadName($pagename, $path);
    $filepath = FmtPageName("$UploadFileFmt/$upname", $pagename);
    if (file_exists($filepath)) break;
  }
  if (!file_exists($filepath)) return;
  $length = filesize($filepath);
  $type = @$UploadExts[preg_replace('/.*\\./', '', $filepath)];
  $url = LinkUpload($pagename, 'Attach:', $path, '', '', '$LinkUrl');
  return "<$k url='$url' length='$length' type='$type' />";
}

SDVA($EntitiesTable, array(
  # entities defined in "http://www.w3.org/TR/xhtml1/DTD/xhtml-lat1.ent"
  '&nbsp;' => '&#160;', 
  '&iexcl;' => '&#161;', 
  '&cent;' => '&#162;', 
  '&pound;' => '&#163;', 
  '&curren;' => '&#164;', 
  '&yen;' => '&#165;', 
  '&brvbar;' => '&#166;', 
  '&sect;' => '&#167;', 
  '&uml;' => '&#168;', 
  '&copy;' => '&#169;', 
  '&ordf;' => '&#170;', 
  '&laquo;' => '&#171;', 
  '&not;' => '&#172;', 
  '&shy;' => '&#173;', 
  '&reg;' => '&#174;', 
  '&macr;' => '&#175;', 
  '&deg;' => '&#176;', 
  '&plusmn;' => '&#177;', 
  '&sup2;' => '&#178;', 
  '&sup3;' => '&#179;', 
  '&acute;' => '&#180;', 
  '&micro;' => '&#181;', 
  '&para;' => '&#182;', 
  '&middot;' => '&#183;', 
  '&cedil;' => '&#184;', 
  '&sup1;' => '&#185;', 
  '&ordm;' => '&#186;', 
  '&raquo;' => '&#187;', 
  '&frac14;' => '&#188;', 
  '&frac12;' => '&#189;', 
  '&frac34;' => '&#190;', 
  '&iquest;' => '&#191;', 
  '&Agrave;' => '&#192;', 
  '&Aacute;' => '&#193;', 
  '&Acirc;' => '&#194;', 
  '&Atilde;' => '&#195;', 
  '&Auml;' => '&#196;', 
  '&Aring;' => '&#197;', 
  '&AElig;' => '&#198;', 
  '&Ccedil;' => '&#199;', 
  '&Egrave;' => '&#200;', 
  '&Eacute;' => '&#201;', 
  '&Ecirc;' => '&#202;', 
  '&Euml;' => '&#203;', 
  '&Igrave;' => '&#204;', 
  '&Iacute;' => '&#205;', 
  '&Icirc;' => '&#206;', 
  '&Iuml;' => '&#207;', 
  '&ETH;' => '&#208;', 
  '&Ntilde;' => '&#209;', 
  '&Ograve;' => '&#210;', 
  '&Oacute;' => '&#211;', 
  '&Ocirc;' => '&#212;', 
  '&Otilde;' => '&#213;', 
  '&Ouml;' => '&#214;', 
  '&times;' => '&#215;', 
  '&Oslash;' => '&#216;', 
  '&Ugrave;' => '&#217;', 
  '&Uacute;' => '&#218;', 
  '&Ucirc;' => '&#219;', 
  '&Uuml;' => '&#220;', 
  '&Yacute;' => '&#221;', 
  '&THORN;' => '&#222;', 
  '&szlig;' => '&#223;', 
  '&agrave;' => '&#224;', 
  '&aacute;' => '&#225;', 
  '&acirc;' => '&#226;', 
  '&atilde;' => '&#227;', 
  '&auml;' => '&#228;', 
  '&aring;' => '&#229;', 
  '&aelig;' => '&#230;', 
  '&ccedil;' => '&#231;', 
  '&egrave;' => '&#232;', 
  '&eacute;' => '&#233;', 
  '&ecirc;' => '&#234;', 
  '&euml;' => '&#235;', 
  '&igrave;' => '&#236;', 
  '&iacute;' => '&#237;', 
  '&icirc;' => '&#238;', 
  '&iuml;' => '&#239;', 
  '&eth;' => '&#240;', 
  '&ntilde;' => '&#241;', 
  '&ograve;' => '&#242;', 
  '&oacute;' => '&#243;', 
  '&ocirc;' => '&#244;', 
  '&otilde;' => '&#245;', 
  '&ouml;' => '&#246;', 
  '&divide;' => '&#247;', 
  '&oslash;' => '&#248;', 
  '&ugrave;' => '&#249;', 
  '&uacute;' => '&#250;', 
  '&ucirc;' => '&#251;', 
  '&uuml;' => '&#252;', 
  '&yacute;' => '&#253;', 
  '&thorn;' => '&#254;', 
  '&yuml;' => '&#255;', 
  # entities defined in "http://www.w3.org/TR/xhtml1/DTD/xhtml-special.ent"
  '&quot;' => '&#34;', 
  #'&amp;' => '&#38;#38;', 
  #'&lt;' => '&#38;#60;', 
  #'&gt;' => '&#62;', 
  '&apos;' => '&#39;', 
  '&OElig;' => '&#338;', 
  '&oelig;' => '&#339;', 
  '&Scaron;' => '&#352;', 
  '&scaron;' => '&#353;', 
  '&Yuml;' => '&#376;', 
  '&circ;' => '&#710;', 
  '&tilde;' => '&#732;', 
  '&ensp;' => '&#8194;', 
  '&emsp;' => '&#8195;', 
  '&thinsp;' => '&#8201;', 
  '&zwnj;' => '&#8204;', 
  '&zwj;' => '&#8205;', 
  '&lrm;' => '&#8206;', 
  '&rlm;' => '&#8207;', 
  '&ndash;' => '&#8211;', 
  '&mdash;' => '&#8212;', 
  '&lsquo;' => '&#8216;', 
  '&rsquo;' => '&#8217;', 
  '&sbquo;' => '&#8218;', 
  '&ldquo;' => '&#8220;', 
  '&rdquo;' => '&#8221;', 
  '&bdquo;' => '&#8222;', 
  '&dagger;' => '&#8224;', 
  '&Dagger;' => '&#8225;', 
  '&permil;' => '&#8240;', 
  '&lsaquo;' => '&#8249;', 
  '&rsaquo;' => '&#8250;', 
  '&euro;' => '&#8364;', 
  # entities defined in "http://www.w3.org/TR/xhtml1/DTD/xhtml-symbol.ent"
  '&fnof;' => '&#402;', 
  '&Alpha;' => '&#913;', 
  '&Beta;' => '&#914;', 
  '&Gamma;' => '&#915;', 
  '&Delta;' => '&#916;', 
  '&Epsilon;' => '&#917;', 
  '&Zeta;' => '&#918;', 
  '&Eta;' => '&#919;', 
  '&Theta;' => '&#920;', 
  '&Iota;' => '&#921;', 
  '&Kappa;' => '&#922;', 
  '&Lambda;' => '&#923;', 
  '&Mu;' => '&#924;', 
  '&Nu;' => '&#925;', 
  '&Xi;' => '&#926;', 
  '&Omicron;' => '&#927;', 
  '&Pi;' => '&#928;', 
  '&Rho;' => '&#929;', 
  '&Sigma;' => '&#931;', 
  '&Tau;' => '&#932;', 
  '&Upsilon;' => '&#933;', 
  '&Phi;' => '&#934;', 
  '&Chi;' => '&#935;', 
  '&Psi;' => '&#936;', 
  '&Omega;' => '&#937;', 
  '&alpha;' => '&#945;', 
  '&beta;' => '&#946;', 
  '&gamma;' => '&#947;', 
  '&delta;' => '&#948;', 
  '&epsilon;' => '&#949;', 
  '&zeta;' => '&#950;', 
  '&eta;' => '&#951;', 
  '&theta;' => '&#952;', 
  '&iota;' => '&#953;', 
  '&kappa;' => '&#954;', 
  '&lambda;' => '&#955;', 
  '&mu;' => '&#956;', 
  '&nu;' => '&#957;', 
  '&xi;' => '&#958;', 
  '&omicron;' => '&#959;', 
  '&pi;' => '&#960;', 
  '&rho;' => '&#961;', 
  '&sigmaf;' => '&#962;', 
  '&sigma;' => '&#963;', 
  '&tau;' => '&#964;', 
  '&upsilon;' => '&#965;', 
  '&phi;' => '&#966;', 
  '&chi;' => '&#967;', 
  '&psi;' => '&#968;', 
  '&omega;' => '&#969;', 
  '&thetasym;' => '&#977;', 
  '&upsih;' => '&#978;', 
  '&piv;' => '&#982;', 
  '&bull;' => '&#8226;', 
  '&hellip;' => '&#8230;', 
  '&prime;' => '&#8242;', 
  '&Prime;' => '&#8243;', 
  '&oline;' => '&#8254;', 
  '&frasl;' => '&#8260;', 
  '&weierp;' => '&#8472;', 
  '&image;' => '&#8465;', 
  '&real;' => '&#8476;', 
  '&trade;' => '&#8482;', 
  '&alefsym;' => '&#8501;', 
  '&larr;' => '&#8592;', 
  '&uarr;' => '&#8593;', 
  '&rarr;' => '&#8594;', 
  '&darr;' => '&#8595;', 
  '&harr;' => '&#8596;', 
  '&crarr;' => '&#8629;', 
  '&lArr;' => '&#8656;', 
  '&uArr;' => '&#8657;', 
  '&rArr;' => '&#8658;', 
  '&dArr;' => '&#8659;', 
  '&hArr;' => '&#8660;', 
  '&forall;' => '&#8704;', 
  '&part;' => '&#8706;', 
  '&exist;' => '&#8707;', 
  '&empty;' => '&#8709;', 
  '&nabla;' => '&#8711;', 
  '&isin;' => '&#8712;', 
  '&notin;' => '&#8713;', 
  '&ni;' => '&#8715;', 
  '&prod;' => '&#8719;', 
  '&sum;' => '&#8721;', 
  '&minus;' => '&#8722;', 
  '&lowast;' => '&#8727;', 
  '&radic;' => '&#8730;', 
  '&prop;' => '&#8733;', 
  '&infin;' => '&#8734;', 
  '&ang;' => '&#8736;', 
  '&and;' => '&#8743;', 
  '&or;' => '&#8744;', 
  '&cap;' => '&#8745;', 
  '&cup;' => '&#8746;', 
  '&int;' => '&#8747;', 
  '&there4;' => '&#8756;', 
  '&sim;' => '&#8764;', 
  '&cong;' => '&#8773;', 
  '&asymp;' => '&#8776;', 
  '&ne;' => '&#8800;', 
  '&equiv;' => '&#8801;', 
  '&le;' => '&#8804;', 
  '&ge;' => '&#8805;', 
  '&sub;' => '&#8834;', 
  '&sup;' => '&#8835;', 
  '&nsub;' => '&#8836;', 
  '&sube;' => '&#8838;', 
  '&supe;' => '&#8839;', 
  '&oplus;' => '&#8853;', 
  '&otimes;' => '&#8855;', 
  '&perp;' => '&#8869;', 
  '&sdot;' => '&#8901;', 
  '&lceil;' => '&#8968;', 
  '&rceil;' => '&#8969;', 
  '&lfloor;' => '&#8970;', 
  '&rfloor;' => '&#8971;', 
  '&lang;' => '&#9001;', 
  '&rang;' => '&#9002;', 
  '&loz;' => '&#9674;', 
  '&spades;' => '&#9824;', 
  '&clubs;' => '&#9827;', 
  '&hearts;' => '&#9829;', 
  '&diams;' => '&#9830;'));

