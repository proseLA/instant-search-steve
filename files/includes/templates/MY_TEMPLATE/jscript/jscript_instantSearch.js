/** v1.0.1 steve edited
 * @package Instant Search Results
 * @copyright Copyright Ayoob G 2009-2011
 * @copyright Portions Copyright 2003-2006 The Zen Cart Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

//This script captures the characters as they are typed into the search box, sends them to searches.php to query the db, and displays the results in a new container below the search box.
//steve
const debug_is_js = false;//log debug info to the console

//these vars will be used to maintain multiple requests
var runningRequest = false;
var request;

//to manually position the result box: set autoPosition = false
//but ensure you provide the top and left value of the results box in instantSearch.css
var autoPosition = true;
var windowWidth;//steve ??
var viewportWidth;//steve ??

var inputboxCurrent;

$(document).ready(function () {
//steve
    if (debug_is_js) {console.log("instant_search debug on")}
//eof
	//this will apply the instant search feature to all the search boxes
    //var inputBox = $('input[name="keyword"]');

    //if you want to add the instant search to a specific search box only, comment out the var inputBox above
    //and uncomment out the specific search box selector below:

    var inputBox = $('#navMainSearch > form[name="quick_find_header"] > input[name="keyword"]');
    //var inputBox = $('#navColumnTwoWrapper > form[name="quick_find_header"] > input[name="keyword"]');
    //var inputBox = $('#searchContent > form[name="quick_find"] > input[name="keyword"]');

    if (debug_is_js) {console.log ('var inputBox='+JSON.stringify(inputBox, null, 4))}//show contents of inputBox

    //this creates a container above the search box, although it displays below it.
    inputBox.before('<div class="resultsContainer"></div>');
    inputBox.attr('autocomplete', 'off');

    //re-position all the instant search containers correctly into their INITIAL positions: but not displayed yet.
    if (autoPosition == true) {
        inputBox.each(function () {
            var offset = $(this).offset();//get the position of THIS particular search box
            //steve
            if (debug_is_js) { console.log ('offset=' + JSON.stringify(offset, null, 4)) }
            if (debug_is_js) { console.log ('inputBox outerWidth=' + $(this).outerWidth(true)) }//width with padding+margin
            //steve added brackets around offset?
            $(this).prev().css("left", (offset.left) + "px");//set lhs edge of the results container
            $(this).prev().css("top", ($(this).outerHeight(true) + offset.top) + "px");//set top edge of the results container (top corner of search box + height of search box)
        });
    }

    //if the search box loses focus: close/hide the instant search container
    inputBox.blur(function () {
        if (inputboxCurrent) {
            var resultsContainer = inputboxCurrent.prev();
            resultsContainer.delay(300).slideUp(200);
        }
    });

	//if we resize the browser or zoom in or out of a page: hide the instant search container until the next search
    $(window).resize(function () {
        getWindowSize();//steve added?
        if (inputboxCurrent) {
            var resultsContainer = inputboxCurrent.prev();
            resultsContainer.hide();
        }
    });

    //the user starts to enter a few characters into the search box
    inputBox.keyup(function () {

        //only the currently selected search box will be used
        inputboxCurrent = $(this);

        //assign a variable to the instant search container
        var resultsContainer = $(this).prev();

        resultsContainer.hide();//steve to clear previous results and allow subsequent re-positioning according to new results (repositioning is done only if results is NOT hidden)

        //capture the characters that are being typed into the search box
        var searchWord = $(this).val();
        var replaceWord = searchWord;

        //clean up the search string to remove any unnecessary characters or double spaces
        searchWord = searchWord.replace(/^\s+/, "");
        searchWord = searchWord.replace(/  +/g, ' ');

        if (searchWord == "") {
            //if the search value entered is empty: hide the instant search container
            resultsContainer.hide();
        } else {
            //if multiple requests are sent to the server, abort the previous request before the new request is sent
            //this only comes into use if the user is typing too quickly
            if (runningRequest) {
                request.abort();
            }

            runningRequest = true;

            //pass the search term to searches.php, this returns the results in an array: "data"
            request = $.getJSON('searches.php', {query: searchWord}, function (data) {

                if (data.length > 0) {//should be always true as even with 0 results, a No Result element is returned
                    var resultHtml = '';//string that will hold the created li
                    var alreadyRan = false;//steve to trigger li hr delimiter between products and categories results

                    $.each(data, function (i, item) {//cycle through results
//steve added all this section
                            if (item.pc == 'p') {//products
                                alreadyRan = false;

                                //create li with link to product
                                resultHtml += '<li><a class="' + item.pc + '" href="' + generateLink(item.pc, item.l) + '">' + highlightSearchMatch(replaceWord, item.q) + '<span class="alignCategoryCount">' + formatNumber(item.c) + '</span></a></li>';

                            } else if (item.pc == 'c') {//categories
                                if (alreadyRan == false) {//first category item only: place hr above to separate it from the products
                                    resultHtml += '<li><hr /></li>';
                                    alreadyRan = true;
                                }
                                //create li with link to category
                                resultHtml += '<li><a class="' + item.pc + '" href="' + generateLink(item.pc, item.l) + '">' + highlightSearchMatch(replaceWord, item.q) + '<span class="alignCategoryCount">: ' + formatNumber(item.c) + '</span></a></li>';

                            } else if (item.pc == 'noResults') {//no result found
                                resultHtml += '<li><a class="' + item.pc + '">' + item.q + '</li>';
                            }

                            if (item.debug != '') {//should be debug_instant_search? debug info - can maybe be seen more clearly in the FFox (not Firebug) Console than as the contents of the array.
                                if (debug_is_js) { console.log ( "item.debug="+item.debug )}
                                if (debug_is_js) { console.log ( 'item.debug='+JSON.stringify(item.debug, null, 4) )}
                            }
                        }
                    );
//eof
                    //wrap the li results with the opening and closing tags
                    resultsContainer.html('<ul>' + resultHtml + '</ul>');

                    if (!resultsContainer.is(':visible')) {//if not hidden when key first pressed, cannot auto position

                        if (autoPosition == true) {//auto position the container if needed
                            autoPositionContainer(inputboxCurrent, resultsContainer);
                        }

                        //drop down instant search box
                        resultsContainer.slideDown(1);//(1) duration in milliseconds
                    }
                } else {//data array is zero length, should never happen
                    resultsContainer.hide();
                }
                runningRequest = false;
            });
        }
    });
});

//this function auto positions the container
//steve improved variable names
function autoPositionContainer(inputBoxCurrent, resultsContainerCurrent) {

    var offsetInput = inputBoxCurrent.offset();//position of inputBox top lhs corner
    if (debug_is_js) {console.log('offsetInput=' + JSON.stringify(offsetInput, null, 4))}
    //steve? not used var inputBoxRight = offsetInput.left + resultsContainerCurrent.outerWidth(true);//position of inputBox rhs edge from right edge of DOCUMENT
//steve all this section
    getWindowSize();

//    if (debug_is_js) {console.log('windowWidth=' + windowWidth)}
    if (debug_is_js) {console.log('viewportWidth=' + viewportWidth)
    }

    //ERROR if (debug_is_js) {console.log ('resultsContainerCssWidth='+resultsContainerCssWidth)}
    var resultsContainerWidth = resultsContainerCurrent.width();
  //  if (debug_is_js) {console.log('resultsContainerWidth=' + resultsContainerWidth)}

    var scrollBarWidth = getScrollBarWidth();
    //if (debug_is_js) {console.log('scrollBarWidth=' + scrollBarWidth)}

    var resultsContainerLeft = viewportWidth - scrollBarWidth - resultsContainerWidth;//calcuate position of box if it butts to right edge of viewport

    if (offsetInput.left + resultsContainerWidth + scrollBarWidth > viewportWidth) {//if results box is under the search box, it overflows to the right
        //if (debug_is_js) {console.log('overflow to right by ' + (offsetInput.left + resultsContainerWidth + scrollBarWidth - viewportWidth))}
        resultsContainerLeft = viewportWidth - scrollBarWidth - resultsContainerWidth;//calculate new position further to the left to just fit to right edge of viewport

    } else {//default, show directly under the search box
        resultsContainerLeft = offsetInput.left;//box under search box
    }

    if (debug_is_js) {console.log('resultsContainerLeft=' + resultsContainerLeft)}
//eof steve
    resultsContainerCurrent.css("left", resultsContainerLeft);//set position of results box
}
//steve new function
function getScrollBarWidth() {//guess
    var $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
        widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
    $outer.remove();
    return 100 - widthWithScroll;
}

//create clickable links from the results
function generateLink(productORcategory, productCategoryID) {
    var l = "";
    if (productORcategory == "p") {//it's a product
        l = "index.php?main_page=product_info&amp;products_id=" + productCategoryID;
    } else {//it's a category
        l = "index.php?main_page=index&amp;cPath=" + productCategoryID;
    }
    return l;
}

//formatting to apply to the section of the result that matches the search term
//steve changed function and name
function highlightSearchMatch(findTxt, replaceTxt) {
    //var f = findTxt.toLowerCase();//original
    //var r = replaceTxt.toLowerCase();//original, produces results in lowercase
    var f = findTxt;
    var r = replaceTxt;//keeps the original case formatting
    var regex = new RegExp('(' + f + ')', 'i');
    return r.replace(regex, '<span class="highlight_search_match">' + f + '</span>')
}

//convert the category/product count string to a number
function formatNumber(num) {
    return num.toString().replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
}
//steve new function
function getWindowSize() {//for use in auto-positioning the results box
    windowWidth = $(document).width();//width of html document
    viewportWidth = $(window).width();//width of browser viewport
}