<?php
/**
 * Created by PhpStorm.
 * User: quinnzhang
 * Date: 10/10/16
 * Time: 12:05 PM
 */

/*
 * Scientist - Internal Links - Bulletin Article and News
 */
function scientist_internal_links_block() {
    // Set a maximum number of links allowed, rest are shown from the 'Read More'.
    $maximum_number_of_news_allowed = 6;

    // Get existing node object
    $node = menu_get_object();
    $node_wrapper = entity_metadata_wrapper('node', $node);

    // Get Field values
    $two_links = $node_wrapper->field_scientist_internal_links->value();

    // Two types of node: news and bulletin articles, id them with different field name of publishing date
    foreach ($two_links as $key => $link){
        if (null !== $link->field_pub_date){
            $nid = $link->nid;
            $internal_links1[$key]['type'] = $link->type;
            $internal_links1[$key]['title'] = $link->title;
            $internal_links1[$key]['date'] = $link->field_pub_date['und']['0']['value'];
            $internal_links1[$key]['url'] = drupal_get_path_alias('node/' . $nid);
            $internal_links1[$key]['status'] = $link->status;
        }

        elseif (null !== $link->field_bulletin_article_pub_date){
            $nid = $link->nid;
            $internal_links2[$key]['type'] = $link->type;
            $internal_links2[$key]['title'] = $link->title;
            $internal_links2[$key]['date'] = $link->field_bulletin_article_pub_date['und']['0']['value'];
            $internal_links2[$key]['url'] = drupal_get_path_alias('node/' . $nid);
            $internal_links2[$key]['status'] = $link->status;
        }
    }

    //merge news and articles array into one for sorting
    $internal_links = array_merge($internal_links1, $internal_links2);

    // Sort links by Post date.

    foreach ($internal_links as $key => $part) {
        $sort[$key] = $part['date'];
    }

    array_multisort($sort, SORT_DESC, $internal_links);

    // Prepare return array.
    $results = array();

    foreach ($internal_links as $key => $inlink) {

        // If Internal Link page is published, proceed
        if ('1' === $inlink['status']) {
            $nid = $inlink->nid;
            $results[$key]['type'] = $inlink['type'];
            $results[$key]['title'] = $inlink['title'];
            $results[$key]['date'] = format_date(strtotime($inlink['date']), 'custom', 'M d, Y');
            $results[$key]['url'] = $inlink['url'];
        }
    }

    // Display predefined # of values defined in $maximum_number_of_news_allowed
    $results = array_slice($results, 0, $maximum_number_of_news_allowed);

    // Start snapping the values in HTMl (Can be a template if needed)
    $output = '<div class="node-type-scientist-internal-links">';
    $output .= '<h2 class="pane-title.">Articles &amp; News</h2>';
    foreach ($results as $values) {
        $output .= '<div class ="' . $values['type'] . ' internal_link">';
        $output .= '<div class ="title-link">' . l($values['title'], $values['url']) . '</div>';
        $output .= '<div class ="dateline"> [<em> ' . $values['date'] . ' </em>] </div>';
        $output .= '</div>';
    }
    $output .= '<div class="more-link"><a href="/news/browse?f[0]=field_news_scientist_node_ref%3A' . $node->nid . '"> View All News › </a></div>';
    $output .= '</div>';
    return $output;
}

/*
 * drupal breadcrumbs: hook, class and template
 */

/**
 * Implements hook_block_info().
 */
function hhmiv2_breadcrumb_block_info() {
    $blocks['hhmiv2_breadcrumb'] = array(
        'info' => t('hhmiv2_breadcrumb'),
        'cache' => DRUPAL_CACHE_GLOBAL,
    );
    return $blocks;
}

/**
 * Implements hook_block_view().
 */
function hhmiv2_breadcrumb_block_view($delta = '') {
    $block = array();

    switch ($delta) {
        case 'hhmiv2_breadcrumb':
            $block_content = HHMIV2Breadcrumbs::hhmiv2getBreadcrumbsArray();
            if (empty($block_content)) {
                return FALSE;
            }
            $block['content'] = $block_content;
            break;
    }
    return $block;

}


/**
 * Implements hook_theme().
 *
 * Provide a list of templates and variables that will be provided to the tpls
 * for each block type.
 */
function hhmiv2_breadcrumb_theme() {
    $theme_info = array();
    $path = drupal_get_path('theme', 'hhmiv2');

    $theme_info['hhmiv2_breadcrumbs'] = array(
        'variables' => array(
            'theme_path' => NULL,
            'breadcrumbs' => NULL,
        ),

        'template' => 'hhmiv2_breadcrumbs',
        'path' => $path . '/components/breadcrumbs',
    );
    return $theme_info;
}


$global_helper_path = drupal_get_path('module', 'global_helper');
require_once($global_helper_path . '/src/global_block.abstract_class.php');

/**
 * Breadcrumbs Block object class.
 *
 * This class is used to display blocks of content that are referred to
 * by a field on the content type.
 */

class HHMIV2Breadcrumbs extends GlobalBlock {

    /**
     * Get breadcrumbs array.
     *
     * @return array
     * Renderable array of values for this block.
     */
    public static function hhmiv2getBreadcrumbsArray() {

        // Let's make sure we are in a node object.
        $node = global_helper_get_current_node();
        if (empty($node)) {
            return;
        }

        //find active news landing page, with latest update
        $node_type = "hhmiv2_news_landing";
        $changed_date = 1;
        $nid = 0;
        //search database for the last changed news landing page
        $result = db_select('node', 'n')
            ->fields('n')
            ->condition('type', $node_type,'=')
            ->condition('status', 0,'>')
            ->execute();
        foreach ($result as $record){
            $changed_date = $changed_date > $record->changed ? $changed_date : $record->changed;
            if ($changed_date == $record->changed){
                $nid = $record->nid;
            }
        }
        $news_landing_url = drupal_get_path_alias('node/' . $nid);

        // Get page title and type
        $gettitle = global_helper_get_entity_field_value($node, 'title');
        $gettype = global_helper_get_entity_field_value($node, 'type');
        // Initialize needed variables.
        $breadcrumbs = array();
        $i = 0;
        // Get page alias url
        $alias = global_helper_get_node_link($node);
        // First breadcrumb is always 'HHMI'. This links to the HHMI homepage.
        $breadcrumbs[$i]['label'] = 'Home';
        $breadcrumbs[$i]['link'] = '/';
        $i++;

        // Find second breadcrumb, if node type is news
        if ('news' === $gettype){
            // If Second breadcrumb is News
            $breadcrumbs[$i]['label'] = 'News';
            $breadcrumbs[$i]['link'] = '/' . $news_landing_url;
            $i++;
            // Third breadcrumb is the Title of the page
            $breadcrumbs[$i]['label'] = $gettitle;
        }
        // if node type is news landing page
        elseif ('hhmiv2_news_landing' === $gettype){
            $breadcrumbs[$i]['label'] = 'News';
        }
        // if node type is other landing pages
        elseif ('hhmiv2_landing' === $gettype){
            // If Second breadcrumb is Landing pages
            $breadcrumbs[$i]['label'] = $gettitle;
            $breadcrumbs[$i]['link'] = $alias;
            $i++;
        }

        $content['#breadcrumbs'] = $breadcrumbs;
        $content['#theme'] = 'hhmiv2_breadcrumbs';

        return $content;
    }
}

//Twig template:
//<ol class="Breadcrumbs">
//    {% for breadcrumb in breadcrumbs %}
//
//                {% if loop.last %}
//                <li class="Breadcrumbs-item Lastbread"><a class="Breadcrumbs-link" href="{{ breadcrumb.link }}">{{ breadcrumb.label }}</a></li>
//        {% else %}
//            <li class="Breadcrumbs-item"><a class="Breadcrumbs-link" href="{{ breadcrumb.link }}">{{ breadcrumb.label }}</a></li>
//        {% endif %}
//
//    {%  endfor %}
//
//</ol>

