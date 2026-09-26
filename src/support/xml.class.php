<?php
/**
 * Adlexone FlowIQ License Agreement 1.0
 * 
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where 
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in. 
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions. 
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */
/**
 * Handles XML IO streams and maps XML to various data sources
 *
 * This class provides methods that provide XML data from data sources such as
 * databases and files, and converts XML to data sources such as databases and
 * files.
 *
 * @author Adam Hall
 */
class XML {
    /**
     * Class variables
     */

    /**
     * Use XML data and descriptors to generate array
     *
     * Using the XML descriptors and data generate the an array for
     * use in data operations including database transactions, file operations,
     * communications etc
     * Class uses PHP 5 SimpleXML extension.
     *
     * @author Adam Hall halla@oneorzero.com
     * @param string $xmlData Contains the XML descriptors and data
     * @param string $dsn DSN connection string for database class
     * @var object $db Database connection object
     * @date 2005-05-22
     * @deprecated
     * @see
     */
    public static function oozCreateArrayFromXML($xmlData, $node)
    {
        // Extract table name from XML
        $xml_array = array();
        foreach ($resource->xpath($node) as $option) {
            $xml_array[$i] = trim($option);
            $i++;
        }

        return $xml_array;
    }


    /**
     * XML::rssFeed()
     *
     * @param mixed $dateFormat
     * @param mixed $rssChannelName
     * @param mixed $rssChannelDescription
     * @param mixed $rssURL
     * @return mixed RSS XML
     */
    public static function rssFeed ($dateFormat, $rssChannelName, $rssChannelDescription, $rssURL, $itemTitleArray, $itemLinkArray)
    {
		//Set header for RSS
        header("Content-Type: application/rss+xml; charset=UTF-8");
		$now = date($dateFormat);

        $html = "<?xml version=\"1.0\"?>
            <rss version=\"2.0\">
                <channel>
                    <title>$rssChannelName</title>
                    <link>$rssURL</link>
                    <description>$rssChannelDescription</description>
                    <language>en-us</language>
                    <pubDate>$now</pubDate>
                    <lastBuildDate>$now</lastBuildDate>";
		//Build page from our two arrays
        $i = 0;
		foreach ($itemTitleArray as $title) {
            $html .= "<item><title>" . htmlentities($title) . "</title>
                    		<link>" . htmlentities($itemLinkArray[$i]) . "</link>
                	</item>";
                	$i++;
        }
        $html .= "</channel>
				</rss>";
        echo $html;
    }
}

?>