<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;


class PostController extends Controller
{
    // Define base URL and endpoints.
    private $baseUrl;
    private $endPoints;

    public function __construct()
    {
        $this->baseUrl = 'https://jsonplaceholder.typicode.com/';
        $this->endPoints = [
            'comments', 'posts'
        ];
    }

    /**
     * This function used to get a list of top posts ordered by their number of comments.
     *
     * @param Request $request
     *
     * @return object
     */
    public function getTopPosts(Request $request)
    {
        // Set the default flag for testing purposes.
        $isToTest = false;

        // If random queryy parameter is passed, set test flag to true.
        if (isset($request->random)) {
            $isToTest = true;
        }

        // Get sorted and formatted post list.
        $getTopPost = $this->getPostList(false, $isToTest);

        return response()->json($getTopPost);
    }

    /**
     * Used to get a list of posts based on filters.
     *
     * @param Request $request
     *
     * @return object
     */
    public function searchPost(Request $request)
    {
        // Set the default flag for testing purposes.
        $isToTest = false;

        // If random queryy parameter is passed, set test flag to true.
        if (isset($request->random)) {
            $isToTest = true;
        }

        // Get sorted and formatted post list with comments.
        $getPostWithComments = $this->getPostList('with_', $isToTest, $request->all());

        return response()->json($getPostWithComments);
    }

    /**
     * Use to make Guzzle call to external API.
     * API results will be stored in the Laravel cache.
     *
     * @param string $endPoint: URI for the request.
     * @param string $prefix: This is used for cache key prefix.
     */
    private function guzzleRequest($endPoint, $prefix = false)
    {
        $guzzleClient = new Client();
        $guzzleRequest = $guzzleClient->request('GET', $this->baseUrl.$endPoint);
        $guzzleResponse = json_decode($guzzleRequest->getBody());

        // Prepend when $prefix is not false.
        $cacheName = $prefix ? $prefix.$endPoint : $endPoint;

        // Store results in the cache.
        Cache::remember($cacheName, now()->addMinutes(15), function () use ($guzzleResponse) {
            return $guzzleResponse;
        });
    }

    /**
     * Used to process the comments to return total number of comments grouped by post id.
     *
     * @param array $allComments: Guzzle /comments response.
     * @param boolean $withContent: Whether to return comment details or not.
     * @param boolean $forExample: To showcase sorting.
     * @param array $filters: Filter arrays.
     *
     * @return array
     */
    private function processComments($allComments, $withContent = false, $forExample = false, $filters = [])
    {
        $postComment = [];
        foreach ($allComments as $commentData) {
            if (isset($postComment[$commentData->postId]['total'])) {
                // Use to add extra random comments.
                if (!$forExample) {
                    $postComment[$commentData->postId]['total']++;
                } else {
                    $postComment[$commentData->postId]['total'] += rand(1, 100);
                }
            } else {
                $postComment[$commentData->postId]['total'] = 1;
            }

            // To only return with content when needed.
            if ($withContent) {
                if ($this->filterComments((array) $commentData, $filters)) {
                    $postComment[$commentData->postId]['body'][$commentData->id] = [
                        'id' => $commentData->id,
                        'name' => $commentData->name,
                        'email' => $commentData->email,
                        'body' => $commentData->body
                    ];
                }
            }
        }

        return $postComment;
    }

    /**
     * Used to process the posts to return in designated format.
     * {
     *  post_id: 1
	 *  post_title: Post Title
	 *  post_body: Post body
	 *  total_number_of_comments: 3
     * }
     *
     * @param array $postData: Guzzle /posts response.
     * @param array $postComments: A group of total number of comments for post.
     * @param mixed $withCommentData: To return with comment list.
     * @param array $filters: Filter parameters.
     *
     * @return array
     */
    private function processPost($postData, $postComments, $withCommentData = false, $filters = [])
    {
        $listOfPost = [];
        foreach ($postData as $post) {
            $postFormat = [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'post_body' => $post->body,
                'total_number_of_comments' => $postComments[$post->id]['total']
            ];

            if ($withCommentData) {
                $commentList = [];
                if (isset($withCommentData[$post->id]['body'])) {
                    $commentList = $withCommentData[$post->id]['body'];
                }

                $postFormat['comments'] = $commentList;
            }

            if ($this->filterComments((array) $postFormat, $filters)) {
                if ($withCommentData) {
                    if (count($postFormat['comments']) > 0) {
                        $listOfPost[] = $postFormat;
                    }
                } else {
                    $listOfPost[] = $postFormat;
                }
            }
        }

        // Sort based on number of comments.
        usort($listOfPost, function ($firstData, $secondData){
            return $firstData['total_number_of_comments'] < $secondData['total_number_of_comments'];
        });

        return $listOfPost;
    }

    /**
     * Used to process the comments and posts.
     * Bind it together in designated format.
     *
     * @param mixed $withContent: Default value will be false. But expected to be string when value assigned in arguement.
     * @param boolean $isToTest: To add extra total number of comments.
     * @param array $filters: Filter parameters.
     *
     * @return array
     */
    private function getPostList($withContent = false, $isToTest = false, $filters = [])
    {
        // Check if 'comments' existed in the cache before making request.
        if (!$this->getDataFromCache($this->endPoints[0], $withContent)) {
            $this->guzzleRequest($this->endPoints[0], $withContent);
        }

        // Get all comments.
        $getAllComments = $this->getDataFromCache($this->endPoints[0], $withContent);

        // Group total number of comments by post id.
        $commentData = $this->processComments($getAllComments, $withContent, $isToTest, $filters);

        // Check if 'posts' existed in the cache before making request.
        if (!$this->getDataFromCache($this->endPoints[1], $withContent)) {
            $this->guzzleRequest($this->endPoints[1], $withContent);
        }

        // Get all posts.
        $unFileteredTopPost = $this->getDataFromCache($this->endPoints[1], $withContent);

        // To determine whether to return with comment data or not.
        $isWithCommentData = $withContent ? $commentData : false;

        // Format the collected data and return in designated format.
        return $this->processPost($unFileteredTopPost, $commentData, $isWithCommentData, $filters);
    }

    /**
     * Filter comments based on comments fields and posts fields.
     *
     * @param array $dataToFilter: Data used to be filtered.
     * @param array $filters: Filters values.
     *
     * @return boolean
     */
    private function filterComments($dataToFilter, $filters)
    {
        if (isset($dataToFilter['postId'])) { // This section used to filter comment based on comment data.
            // Filter by post id.
            $isPostId = $this->filterValidation($filters, 'post_id', $dataToFilter['postId'], true)['filter'];
            $postId = $this->filterValidation($filters, 'post_id', $dataToFilter['postId'], true)['result'];

            // Filter by id.
            $isId = $this->filterValidation($filters, 'id', $dataToFilter['id'], true)['filter'];
            $id = $this->filterValidation($filters, 'post_id', $dataToFilter['id'], true)['result'];

            // Filter by name.
            $isName = $this->filterValidation($filters, 'name', $dataToFilter['name'])['filter'];
            $name = $this->filterValidation($filters, 'name', $dataToFilter['name'])['result'];

            // Filter by email.
            $isEmail = $this->filterValidation($filters, 'email', $dataToFilter['email'])['filter'];
            $email = $this->filterValidation($filters, 'email', $dataToFilter['email'])['result'];

            // Filter by body.
            $isBody = $this->filterValidation($filters, 'body', $dataToFilter['body'])['filter'];
            $body = $this->filterValidation($filters, 'body', $dataToFilter['body'])['result'];

            // Check if post id valid.
            if ($isPostId && !$postId) {
                return false;
            }

            // Check if id valid.
            if ($isId && !$id) {
                return false;
            }

            // Check if name valid.
            if ($isName && !$name) {
                return false;
            }

            // Check if email valid.
            if ($isEmail && !$email) {
                return false;
            }

            // Check if body valid.
            if ($isBody && !$body) {
                return false;
            }

            return true;
        } else { // This section used to filter comment based on post data.
            // Filter by post title.
            $isPostTitle = $this->filterValidation($filters, 'post_title', $dataToFilter['post_title'])['filter'];
            $postTitle = $this->filterValidation($filters, 'post_title', $dataToFilter['post_title'])['result'];

            // Filter by post body.
            $isPostBody = $this->filterValidation($filters, 'post_body', $dataToFilter['post_body'])['filter'];
            $postBody = $this->filterValidation($filters, 'post_body', $dataToFilter['post_body'])['result'];

            // Check if post title valid.
            if ($isPostTitle && !$postTitle) {
                return false;
            }

            // Check if post body valid.
            if ($isPostBody && !$postBody) {
                return false;
            }

            return true;
        }
    }

    /**
     * Used to validate the filter.
     *
     * @param string $haystack: Long string to be searched.
     * @param string $needle: Word to be searched from the long string.
     * @param boolean $isExact: To validate the exact match.
     *
     * @return array
     */
    private function filterValidation($needle, $needleKey, $haystack, $isExact = false)
    {
        $validationResponse = [
            'filter' => false,
            'result' => false
        ];

        if (isset($needle[$needleKey])) {
            $validationResponse['filter'] = true;
            $needle = $needle[$needleKey];

            // Validate string contains.
            if (!$isExact) {
                if (strpos(strtolower($haystack), strtolower($needle)) !== false) {
                    $validationResponse['result'] = true;
                }
            } else {
                if ($haystack == $needle) {
                    $validationResponse['result'] = true;
                }
            }
        }

        return $validationResponse;
    }

    /**
     * Used to get the cache data.
     * If cache exists, it will return the value. Or else it will return false.
     *
     * @param string $cacheName: Cache key name
     * @param mixed $prefix: Default value will be false. But expected to be string when value assigned in arguement.
     *
     * @return mixed
     */
    private function getDataFromCache($cacheName, $prefix = false)
    {
        // Prepend when $prefix is not false.
        $cacheName = $prefix ? $prefix.$cacheName : $cacheName;

        // Check if the cache name exists in the cache.
        if (Cache::has($cacheName)) {
            return Cache::get($cacheName);
        }

        return false;
    }
}
