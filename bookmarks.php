<?php
/**
 * Class to handle the question mark functionality and minimiz database calls
 */

class DatBookmarks implements ArrayAccess {

	private $data = [];
	private $mapped_data = [];
	private $valid_statuses = [];

	private static $instance;

	/**
	 * Run this as a Singleton to maintain one data set
	 *
	 * @return DatBookmarks
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new datBookmarks();

		}

		return self::$instance;
	}

	/**
	 * Used for the DatGlobal passing of Data to Javascript
	 * 
	 * @param array $args cid, qcid to determine which to render
	 * 
	 * @return array|int
	 */
	public function getSavedBookmarksTallyForGlobal($args){
		if(isset($args['cid']) && $args['cid'] != 0){
			$taxonomy_id=$args['cid'];
			$mapped_data=$this->mapQuizCategoryQuestions();

		} else if(isset($args['qcid']) && $args['qcid']!=0){
			$taxonomy_id=$args['qcid'];
			$mapped_data=$this->mapQuestionsByQuestionCategory();
		} else {
			$taxonomy_id=0;
		}
		if($taxonomy_id===0){
			return [];
		}
		
		return $this->mappedQuestionsToTotals($mapped_data,$taxonomy_id);
	}

	/**
	 * Constructor
	 * 
	 * @return void
	 */
	private function __construct() {
		// get current user id
		$user_id = get_current_user_id();

		// get user bookmark
		$this->data = get_user_meta( $user_id, 'dat_marked_questions', true );

		$this->data = $this->get_array( $this->data );

		$this->valid_statuses = [ 'learning', 'reviewed' ];
	}

	/**
	 * Array Access Interface Method
	 * 
	 * @param mixed $var
	 * @param string $delimiter
	 * 
	 * @return mixed
	 */
	private function get_array( $var = false, $delimiter = '' ) {

		// array
		if ( is_array( $var ) ) {
			return $var;
		}

		// bail early if empty
		if ( ! $var && ! is_numeric( $var ) ) {
			return [];
		}

		// string
		if ( is_string( $var ) && $delimiter ) {
			return explode( $delimiter, $var );
		}

		// place in array
		return (array) $var;

	}

	/**
	 * Saves Bookmarks back to database
	 *
	 * @return void
	 */
	public function save() {
		update_user_meta( get_current_user_id(), 'dat_marked_questions', $this->data );
	}


	/**
	 * Array Access Interface method	 
	 * 
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Sort Bookmarks by time of creation
	 * 
	 * @return void
	 */
	public function sortByMarkedTime() {
		uasort( $this->data, function ( $a, $b ) {
			//backwards compatible for non-arrays
			if ( ! is_array( $a ) || ! is_array( $b ) ) {
				return 0;
			}

			if ( $a['marked_time'] == $b['marked_time'] ) {
				return 0;
			}

			return ( $a['marked_time'] < $b['marked_time'] ) ? - 1 : 1;
		} );
	}

	/**
	 * Maps marked questions to array keyed by Quiz Category Id
	 * 
	 * @return array
	 */
	private function mapQuizCategoryQuestions() {
		if ( ! empty( $this->mapped_data ) ) {
			return $this->mapped_data;
		}

		$questionMapper = new WpProQuiz_Model_QuestionMapper;

		$questionMapper = new WpProQuiz_Model_QuestionMapper;
        $quizMapper= new WpProQuiz_Model_QuizMapper;
		$quiz_categories = get_terms( [
			'taxonomy'   => 'ld_quiz_category',
			'hide_empty' => false,
		] );

		$mapped = array_fill_keys( array_map( function ( $term ) {
			return $term->term_id;
		}, $quiz_categories ), [] );

		foreach ( $this->data as $question_id => $array ) {
			$question = $questionMapper->fetchById( $question_id, 1 );

			if ( empty( $question ) ) {
				continue;
			}
			//backwards compatibility
			if ( ! is_array( $array ) ) {
				$array = [
					'question_post_id' => $array,
				];
			}

            if($array['question_post_id']){
                $quiz_post_id=get_post_meta($array['question_post_id'], 'quiz_id', true);
            } else {
                $quiz = $quizMapper->fetch( $question->getQuizId() );
                $quiz_post_id=learndash_get_quiz_id_by_pro_quiz_id($quiz->getId());
            }

            $quiz_categories = wp_get_post_terms( $quiz_post_id, 'ld_quiz_category' );

			$quiz_category = is_array( $quiz_categories ) && ! empty( $quiz_categories ) ? $quiz_categories[0]->term_id : 0;

            $quiz_questions=get_post_meta($quiz_post_id,'ld_quiz_questions', true) ?: [];

			$array['question_category']               = $question->getCategoryId();
			$array['quiz_name']                       = get_the_title( $quiz_post_id );
			$array['quiz_post_id']                    = $quiz_post_id;
			$array['sort']                            = array_search($question_id, array_values($quiz_questions))+1;
			$mapped[ $quiz_category ][ $question_id ] = $array;
		}

		$this->mapped_data = $mapped;

		return $mapped;
	}

	/**
	 * Maps marked questions to array keyed by Question Category Id
	 * 
	 * @return array
	 */
	private function mapQuestionsByQuestionCategory(){
		$this->mapQuizCategoryQuestions();
		$questions=array_replace(...$this->mapped_data);
		

		$out=array_fill_keys(
			array_unique(
				array_map(function($arr){return $arr['question_category'];}, $questions)
			),
			[]);

		foreach($questions as $question_id=>$arr){
			$out[$arr['question_category']][$question_id]=$arr;
		}
		
		return $out;
	}
	/**
	 * Resets the mapped_data property
	 */

    public function resetMappedData(){
        $this->mapped_data=[];
    }

	/**
	 * Get marked questions for a specific Quiz category
	 *
	 * @param int $term_id
	 * @return array
	 */
	public function getQuizCategoryQuestions( $term_id ) {
		$this->mapQuizCategoryQuestions();
		if ( ! isset( $this->mapped_data[ $term_id ] ) ) {
			new WP_Error( "$term_id Quiz Category shows no marked questions" );

			return [];
		}

		return $this->mapped_data[ $term_id ];
	}

	/**
	 * Get marked questions for a specific question category
	 *
	 * @param int $cat_id
	 * @return array
	 */
	public function getQuestionCategoryQuestions( $cat_id){
		$mapped=$this->mapQuestionsByQuestionCategory();
		return isset($mapped[$cat_id]) ? $mapped[$cat_id] : [];
	}

	/**
	 * Get tally for a quiz category
	 *
	 * @param int $term_id
	 * @return array
	 */
	public function getQuizCategoryTotals( $term_id ) {
		$this->mapQuizCategoryQuestions();

		return $this->mappedQuestionsToTotals($this->mapped_data, $term_id);
	}

	/**
	 * Get tally for a question category
	 *
	 * @param int $category_id
	 * @return array
	 */
	public function getQuestionCategoryTotals($category_id){
		$mapped_questions=$this->mapQuestionsByQuestionCategory();
		return $this->mappedQuestionsToTotals($mapped_questions, $category_id);
	}

	/**
	 * Create Tally for mapped data
	 *
	 * @param array $mapped_questions
	 * @param int $taxonomy_id
	 * @return array
	 */
	private function mappedQuestionsToTotals($mapped_questions, $taxonomy_id){
		$totals = [
			'total'    => 0,
			'learning' => 0,
			'reviewed' => 0,
		];

		if(!isset($mapped_questions[$taxonomy_id])){
			return $totals;
		}
		$questions = $mapped_questions[ $taxonomy_id ];

		foreach ( $questions as $question_data ) {
			$totals['total'] ++;

			//backwards compatible
			if ( ! is_array( $question_data ) ) {
				continue;
			}

			if ( isset( $question_data['learning'] ) && $question_data['learning'] ) {
				$totals['learning'] ++;
			} else if ( isset( $question_data['reviewed'] ) && $question_data['reviewed'] ) {
				$totals['reviewed'] ++;
			}
		}

		return $totals;
	}

	/**
	 * Add a question to user bookmarks
	 *
	 * @param int $question_id
	 * @param int $question_post_id
	 * @return void
	 */
	public function markQuestion( $question_id, $question_post_id ) {
		$entry = [
			'question_post_id' => $question_post_id,
			'marked_time'      => time(),
		];

		$this->data[ $question_id ] = isset( $this->data[ $question_id ] ) && is_array( $this->data[ $question_id ] ) ? array_merge( $this->data[ $question_id ], $entry ) : $entry;

	}

	/**
	 * Remove a question from user bookmarks
	 * 
	 * @param int $question_id
	 */
	public function unmarkQuestion( $question_id ) {
		if ( isset( $this->data[ $question_id ] ) ) {
			unset( $this->data[ $question_id ] );
		}
	}

	/**
	 * Add a special tag to a marked question
	 *
	 * @param int $question_id
	 * @param string $status
	 * @return void
	 */
	public function setQuestionStatus( $question_id, $status ) {

		if ( ! isset( $this->data[ $question_id ] ) ) {
			new WP_Error( "Cannot tag an non-bookmarked question" );

			return;
		}

		if ( ! in_array( $status, $this->valid_statuses ) ) {
			new WP_Error( "$status is an invalid marked question status" );

			return;
		}
		//backwards compatibility
		if ( ! is_array( $this->data[ $question_id ] ) ) {
			$this->markQuestion( $question_id, $this->data[ $question_id ] );
		}

		//assume there can be only one
		foreach ( $this->valid_statuses as $value ) {
			$this->data[ $question_id ][ $value ] = $value === $status ? 1 : 0;
		}

	}

	/**
	 * Remove a special tag from a marked question
	 *
	 * @param int $question_id
	 * @param string $status
	 * @return void
	 */
	private function unsetQuestionStatus( $question_id, $status ) {

		$this->data[ $question_id ][ $status ] = 0;
	}

	/**
	 * ArrayAccess implementation method maps array access to data property
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->data[] = $value;
		} else {
			$this->data[ $offset ] = $value;
		}
	}

	/**
	 * ArrayAcceess implementation
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		return isset( $this->data[ $offset ] );
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetUnset( $offset ) {
		unset( $this->data[ $offset ] );
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet( $offset ) {
		return isset( $this->data[ $offset ] ) ? $this->data[ $offset ] : null;
	}

}
