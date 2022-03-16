<?php


/**
 * Get the appropriate image for the site. Fallback to Booster images if none is found.
 *
 * @param  string $filename Image filename
 * @return string           URI to file
 */
function dat_get_theme_image($filename) {
    $name = substr($filename, 0, strpos($filename, '.'));
    $setting = dat_get_setting($name);

    if ($setting) {
        return $setting;
    }

    switch (dat_get_site_name()) {
        // Crusher, Temp, and Staging
        case 'datcrusher':
            $dir = '/img/src/crusher/';

            if (file_exists(DAT_BOOSTER_DIR . $dir . $filename)) {
                return get_stylesheet_directory_uri() . $dir . $filename;
            }
        break;

        case 'oatbooster':
        $dir = '/img/src/oatbooster/';

        if (file_exists(DAT_BOOSTER_DIR . $dir . $filename)) {
            return get_stylesheet_directory_uri() . $dir . $filename;
        }
        break;
    }
    // booster and fallback
    return get_stylesheet_directory_uri() . "/img/src/$filename";
}

/**
 * Get the sitename for this custom child theme
 *
 * @param  string $filename Image filename
 * @return string           URI to file
 */
function dat_get_site_name() {

    if(defined('SITE_NAME_OVERRIDE')) {
        return SITE_NAME_OVERRIDE;
    }

    $url = site_url();

    switch ($url) {
        // Crusher, Temp, and Staging
        case 'https://datcrusher.ca':
        case 'https://06d8506953.nxcli.net':
        case 'https://datbooster.com':
        case 'https://805f13afdf.nxcli.net/':
            return 'datbooster';

        case 'https://oatbooster.com':
        case 'https://1c8a5b0e45.nxcli.net':
            return 'oatbooster';

        case 'https://patbooster.com':
        case 'https://171aee5854.nxcli.net':
        case 'https://fe2c866f15.nxcli.net':
            return 'patbooster';

        default:
            return 'datbooster';
    }
}


function dat_get_test_name(){
    $site_name=dat_get_site_name();
    if('oatbooster'===$site_name){
        return 'OAT';
    } else if('patbooster'===$site_name){
        return 'PAT';
    } else {
        return 'DAT';
    }
}

function dat_get_academic_average_description(){
    switch(dat_get_test_name()){
        case 'OAT':
            return 'Academic Average is the average score of all 6 subjects in the full length test.';
            break;

        default:
            return 'Academic Average is the average score of the Biology, General Chemistry, Organic Chemistry, Reading Comprehension, and Quantitative Reasoning scores. This score does not include Perceptual Ability.';
    }
}



function dat_get_production_site_url() {
    $name = dat_get_site_name();

    if ($name === 'datcrusher') {
        return 'https://datcrusher.ca';
    }

    if ($name === 'datbooster') {
        return 'https://datbooster.com';
    }
    if($name === 'oatbooster') {
        return 'https://oatbooster.com';
    }
}

function dat_get_site_quiz_categories(){
    switch(dat_get_test_name()){
        case 'PAT':
            return [
                'practice-tests'
            ];
            
        case 'OAT':
            return [
                'biology',
                'general-chemistry',
                'organic-chemistry',
                'reading-comprehension',
                'physics',
                'quantitative-reasoning'
            ];

        default:
        return [
            'biology',
            'general-chemistry',
            'organic-chemistry',
            'perceptual-ability',
            'reading-comprehension',
            'quantitative-reasoning'
        ];
    }
}

function dat_get_site_question_categories(){
    switch(dat_get_test_name()){
        case 'PAT':
            return [
                'Keyholes',
                'Top-Front-End',
                'Angle-Ranking',
                'Hole-Punching',
                'Cube-Counting',
                'Pattern-Folding',
            ];
        default:
            return [];
    }
}

function dat_get_ordered_question_categories(){
    $categories=array_filter(
        (new WpProQuiz_Model_CategoryMapper())->fetchAll(), 
        function($proCategory){
            return in_array($proCategory->getCategoryName(), dat_get_site_question_categories());
    });

    usort($categories, function($a_proCategory, $b_proCategory){
        $order=dat_get_site_question_categories();
        $a=array_search($a_proCategory->getCategoryName(), $order);
        $b=array_search($b_proCategory->getCategoryName(), $order);
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    });

    return $categories;
}

function dat_get_ordered_quiz_categories(){
    $site_ordering=dat_get_site_quiz_categories();
    
    $quiz_categories=array_filter( 
        get_terms( [
        'taxonomy'=>'ld_quiz_category',
        'hide_empty'=>false
        ]), 
        function($term) use ($site_ordering){
            return in_array($term->slug, $site_ordering);
        });

    usort($quiz_categories, function($a_term, $b_term) use ($site_ordering){
        $a=array_search($a_term->slug,$site_ordering);
        $b=array_search($b_term->slug,$site_ordering);
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    });

    return $quiz_categories;
}

