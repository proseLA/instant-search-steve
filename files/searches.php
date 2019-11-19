<?php //1.0.1 steve edited, POSM
/**
 * @package Instant Search Results
 * @copyright Copyright Ayoob G 2009-2011
 * @copyright Portions Copyright 2003-2006 The Zen Cart Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */


//This PHP file is used to get the search results from our database. 

//steve added
//what results do you want to display in the search?
$products_show = 1;//show products in the results: 1 or 0
$products_results_max = 10;//how many products to show in the list: integer
$categories_show = 0;//show categories in the list: 1 or 0
$categories_show_count = 0;//show count in the categories list: 1 or 0
$categories_list = 0;//how many categories to show in the list: integer
$searchProduct = true;
$searchOptionNames = true;

// I don't know if this is necessary
header('Content-type: text/html; charset=utf-8');

//need to add this
require('includes/application_top.php');
global $db;

//steve
//is Products Options Stock Manager installed? Expand search to include model numbers defined in this mod's table
$posmInUse = defined('POSM_ENABLE') && POSM_ENABLE == 'true' ? true : false;
//$posmInUse = false;

//this gets the word we are searching for. Usually from instantSearch.js.
$wordSearch = (isset($_GET['query']) ? $_GET['query'] : '');
//not used $debug = ( (isset($_GET['debug']) && $_GET['debug'] == 'true') ? true : false);

// we place our results into these arrays
//$results will hold data that has the search term in the BEGINNING of the word. This will yield a better search result but the number of results will be fewer.
//$resultsAddAfter will hold data that has the search term ANYWHERE in the word. This will yield a normal search result but the number of results will be high.
//$results has priority over $resultsAddAfter
$results = array();
$resultsAddAfter = array();
//steve added
$prodResultText = '';
$resultsProductsPrimary = array();
$resultInPrimary = false;
$resultsProductsSecondary = array();
$debugInfo = '';
//eof steve
//the search word cannot be empty
if (strlen($wordSearch) > 0) {
//steve use filter in sql not here
    //if the user enters less than 2 characters we would like to match search results that begin with these characters
    //if the characters are greater than 2 then we would like to broaden our search results
    //if (strlen($wordSearch) <= 2) {
    ///    $wordSearchPlus = $wordSearch . "%";//get results that START with the string
   // } else {
        $wordSearchPlus = "%" . $wordSearch . "%";//get results that INCLUDE with the string
    //}

    //first we would like to search for products that match our search word
    //we then order the search results with respect to the keyword found at the beginning of each of the results
//steve changed all this    
    if ($products_show == 1) {
        if ($searchProduct) {/** @noinspection SqlResolve */
            $sqlProducts = "SELECT pd.products_name, pd.products_id, p.products_model, p.products_ordered
                        FROM " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS . " p 
                        WHERE (
                        (pd.products_name LIKE :wordSearchPlus:) OR 
                        (p.products_model LIKE :wordSearchPlus:)
                        )
                        AND p.products_status <> 0
                        AND pd.language_id = :languagesId: 
                        AND p.products_id = pd.products_id
                        GROUP BY pd.products_id 
                        ORDER BY 
                        field(LEFT(pd.products_name,LENGTH(:wordSearch:)), :wordSearch:) DESC, 
                        p.products_ordered DESC
                        LIMIT $products_results_max"; //limit results

            //this protects from sql injection - I think????
            $sqlProducts = $db->bindVars($sqlProducts, ':languagesId:', $_SESSION['languages_id'], 'integer');
            $sqlProducts = $db->bindVars($sqlProducts, ':wordSearch:', $wordSearch, 'string');
            $sqlProducts = $db->bindVars($sqlProducts, ':wordSearchPlus:', $wordSearchPlus, 'string');

            $dbProducts = $db->Execute($sqlProducts);

            if ($dbProducts->RecordCount() > 0) {
//steve changed
                foreach ($dbProducts as $row) {
                    $resultName = strip_tags($row['products_name']);
                    $resultModel = strip_tags($row['products_model']);//steve added

                    switch (true) {

                        case (mb_stripos($resultName, $wordSearch) === 0); //is the wordSearch at the START of the product name?
                            $prodResultText = $resultName . ' (' . $resultModel . ')';
                            $resultInPrimary = true;
                            $debugInfo = 'Product: START of name';
                            break;

                        case (mb_stripos($resultName, $wordSearch) !== false); //is the wordSearch IN the product name?
                            $prodResultText = $resultName . ' (' . $resultModel . ')';
                            $resultInPrimary = false;
                            $debugInfo = 'Product: IN name';
                            break;

                        case (mb_stripos($resultModel, $wordSearch) === 0); //is the wordSearch at the START of the product model?
                            $prodResultText = $resultModel . ' - ' . $resultName;
                            $resultInPrimary = true;
                            $debugInfo = 'Product: START of model';
                            break;

                        case (mb_stripos($resultModel, $wordSearch) !== false); //is the wordSearch IN the product model?
                            $prodResultText = $resultModel . ' - ' . $resultName;
                            $resultInPrimary = false;
                            $debugInfo = 'Product: IN model';
                            break;

                        default:
                            break;
                    }
                    /*
                    The results are split into two arrays:
                    - the first where the wordSearch was found at the START of the string
                    - the second where the wordSearch was SOMEWHERE in the string.
                    The second array is added onto the end of the first to display/group the results separately.
                    Five variables are passed back to instantSearch.js
                    'q' is the text of the result that has been found
                    'c' is the number of matching items within a category search (we leave this empty for product search, look at the example below for category search)
                    'l' is the id of the product/category, used for creating a link to the product or category
                    'pc' identifies the result as a product p or a category c
                    'debug' you can use to include debugging info in the console along with the previous four.
                    */

                    if ($resultInPrimary) {
                        $resultsProductsPrimary[] = array(
                            'q' => $prodResultText,
                            'c' => "",
                            'l' => $row['products_id'],
                            'pc' => "p"
                        , 'debug' => '1: ' . $debugInfo //$dbProducts //steve to pass debug info back in the response
                        );
                    } else {//result in somewhere in the string
                        $resultsProductsSecondary[] = array(
                            'q' => $prodResultText,
                            'c' => "",
                            'l' => $row['products_id'],
                            'pc' => "p"
                        , 'debug' => '2: ' . $debugInfo//steve to pass debug info back in the response
                        );
                    }
                }
            }
        }
    }
    if (sizeof($resultsProductsPrimary) < $products_results_max && $searchOptionNames) {//search option names
        /** @noinspection SqlResolve */
        $sqlOptionNames = "SELECT p.products_id, p.products_model, pd.products_name, pov.products_options_values_name 
FROM " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_ATTRIBUTES . " pa ," . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
                                WHERE (pov.products_options_values_name LIKE :wordSearchPlus:)
                                AND pov.language_id = :languagesId:
                                AND pd.language_id = :languagesId:
                                AND p.products_id = pa.products_id
                                AND pd.products_id = pa.products_id
                                AND p.products_status <> 0
                                AND pa.options_values_id = pov.products_options_values_id
                                LIMIT $products_results_max"; //limit results

        $sqlOptionNames = $db->bindVars($sqlOptionNames, ':languagesId:', $_SESSION['languages_id'], 'integer');
        $sqlOptionNames = $db->bindVars($sqlOptionNames, ':wordSearch:', $wordSearch, 'string');
        $sqlOptionNames = $db->bindVars($sqlOptionNames, ':wordSearchPlus:', $wordSearchPlus, 'string');
        $dbOptionNames = $db->Execute($sqlOptionNames);

        if ($dbOptionNames->RecordCount() > 0) {
            foreach ($dbOptionNames as $row) {
                $resultName = strip_tags($row['products_name']);
                $resultModel = strip_tags($row['products_model']);
                $resultOptionValueName = strip_tags($row['products_options_values_name']);

                switch (true) {

                    case (mb_stripos($resultOptionValueName, $wordSearch) === 0); //is the wordSearch at the START of the option value name?
                        $prodResultText = '(' . $resultOptionValueName . ') ' . $resultModel . ' - ' . $resultName . ' ';
                        $resultInPrimary = true;
                        $debugInfo = 'Option: START of option name';
                        break;

                    case (mb_stripos($resultName, $wordSearch) !== false); //is the wordSearch in the option value name?
                        $prodResultText = $resultName . ' (' . $resultModel . ')';
                        $resultInPrimary = false;
                        $debugInfo = 'Option: IN option name';
                        break;

                    default:
                        break;
                }

                if ($resultInPrimary) {
                    $resultsProductsPrimary[] = array(
                        'q' => $prodResultText,
                        'c' => "",
                        'l' => $row['products_id'],
                        'pc' => "p"
                    , 'debug' => '1: ' . $debugInfo //steve to pass debug info back in the response
                    );
                } else {//result in somewhere in the string
                    $resultsProductsSecondary[] = array(
                        'q' => $prodResultText,
                        'c' => "",
                        'l' => $row['products_id'],
                        'pc' => "p"
                    , 'debug' => '2: ' . $debugInfo //steve to pass debug info back in the response
                    );
                }
            }
        }
    }

    if (sizeof($resultsProductsPrimary) < $products_results_max && $posmInUse) {//search POSM models
        /** @noinspection SqlResolve */
        $sqlPosmProduct = "SELECT p.products_id, pd.products_name, pos.pos_model FROM " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_OPTIONS_STOCK . " pos 
            WHERE (pos.pos_model LIKE :wordSearchPlus:)
            AND pd.products_id = pos.products_id
            AND p.products_id = pos.products_id
            AND p.products_status <> 0
            AND pd.language_id = :languagesId:
            LIMIT $products_results_max"; //limit results

        $sqlPosmProduct = $db->bindVars($sqlPosmProduct, ':languagesId:', $_SESSION['languages_id'], 'integer');
        $sqlPosmProduct = $db->bindVars($sqlPosmProduct, ':wordSearchPlus:', $wordSearchPlus, 'string');
        $dbPosmProduct = $db->Execute($sqlPosmProduct);

        if ($dbPosmProduct->RecordCount() > 0) {
            foreach ($dbPosmProduct as $row) {
                $resultName = strip_tags($row['products_name']);
                $resultPosmModel = strip_tags($row['pos_model']);

                switch (true) {

                    case (mb_stripos($resultPosmModel, $wordSearch) === 0); //is the wordSearch at the START of the Posm Model?
                        $prodResultText = $resultPosmModel . ' - ' . $resultName . ' ';
                        $resultInPrimary = true;
                        $debugInfo = 'Posm: START of posm model';
                        break;

                    case (mb_stripos($resultPosmModel, $wordSearch) !== false); //is the wordSearch IN the Posm model?
                        $prodResultText = $resultPosmModel . ' - ' . $resultName . ' ';
                        $resultInPrimary = false;
                        $debugInfo = 'Posm: IN posm model';
                        break;

                    default:
                        break;
                }

                if ($resultInPrimary) {
                    $resultsProductsPrimary[] = array(
                        'q' => $prodResultText,
                        'c' => "",
                        'l' => $row['products_id'],
                        'pc' => "p"
                    , 'debug' => '1: ' . $debugInfo //steve to pass debug info back in the response
                    );
                } else {//result in somewhere in the string
                    $resultsProductsSecondary[] = array(
                        'q' => $prodResultText,
                        'c' => "",
                        'l' => $row['products_id'],
                        'pc' => "p"
                    , 'debug' => '2: ' . $debugInfo //steve to pass debug info back in the response
                    );
                }
            }
        }
    }
//steve eof!!
//similar to product search but now we search within categories
    if ($categories_show == 1) {

//c.categories status is for disabled categories
        /** @noinspection SqlResolve */
        $sqlCategories = "SELECT cd.categories_name, cd.categories_id, c.categories_status
FROM " . TABLE_CATEGORIES_DESCRIPTION . " cd 
INNER JOIN " . TABLE_CATEGORIES . " c 
ON cd.categories_id = c.categories_id
WHERE (c.categories_status = 1) 
AND cd.language_id = '" . (int)$_SESSION['languages_id'] . "'
AND ((cd.categories_name  LIKE :wordSearchPlus:) 
OR (LEFT(cd.categories_name,LENGTH(:wordSearch:)) SOUNDS LIKE :wordSearch:))
ORDER BY field(LEFT(cd.categories_name,LENGTH(:wordSearch:)), :wordSearch:) DESC 
LIMIT $categories_list";

        $sqlCategories = $db->bindVars($sqlCategories, ':wordSearch:', $wordSearch, 'string');
        $sqlCategories = $db->bindVars($sqlCategories, ':wordSearchPlus:', $wordSearchPlus, 'string');

        $dbCategories = $db->Execute($sqlCategories);

        if ($dbCategories->RecordCount() > 0) {
            foreach ($dbCategories as $row) {
                //this searches for the number of products within a category
                if ($categories_show_count == 0) {//show the category count or not
                    $products_count = '';//set the count to empty (no display)
                } else {
                    $products_count = zen_count_products_in_category($row['categories_id']); //or get number
                };

                $prodResultText = strip_tags($row['categories_name']);

                if (strtolower(substr($prodResultText, 0, strlen($wordSearch))) == strtolower($wordSearch)) {
                    $results[] = array(
                        'q' => $prodResultText,
                        'c' => $products_count,//steve added space
                        'l' => zen_get_generated_category_path_rev($row['categories_id']),
                        'pc' => "c"
                    );
                } else {
                    $resultsAddAfter[] = array(
                        'q' => $prodResultText,
                        'c' => $products_count,//steve added space
                        'l' => zen_get_generated_category_path_rev($row['categories_id']),
                        'pc' => "c"
                    );
                }
            }
        }
    }
}

//we now add the secondary onto the primary
foreach ($resultsProductsSecondary as &$value) {
    $resultsProductsPrimary[] = array(
        'q' => $value["q"],
        'c' => $value["c"],
        'l' => $value["l"],
        'pc' => $value["pc"],
        'debug' => $value["debug"]
    );
}
unset($value);

//bof steve array sorting to put results in order of products, then categories
function array_sort_by_column(&$arr, $col, $dir = SORT_ASC)
{
    $sort_col = array();
    foreach ($arr as $key => $row) {
        $sort_col[$key] = $row[$col];
    }
    array_multisort($sort_col, $dir, $arr);
}
if (sizeof($resultsProductsPrimary) == 0 ){
    $resultsProductsPrimary[] = array(
        'q' => PLUGIN_INSTANT_SEARCH_NO_RESULTS,
        'c' => "",
        'l' => "",
        'pc' => "noResults",
        'debug' => "no results found"
    );
}
//array_sort_by_column($results, 'pc', SORT_DESC);
//eof steve array sorting

//the results are now passed onto instantSearch.js
echo json_encode($resultsProductsPrimary);
