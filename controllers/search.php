<?php

use Symfony\Component\HttpKernel\HttpKernelInterface;

use \DateTime;

use GW2Spidy\Application;
use Symfony\Component\HttpFoundation\Request;

use GW2Spidy\DB\DisciplineQuery;
use GW2Spidy\DB\ItemSubTypeQuery;
use GW2Spidy\DB\ItemType;
use GW2Spidy\DB\RecipeQuery;
use GW2Spidy\DB\GW2Session;
use GW2Spidy\DB\GoldToGemRateQuery;
use GW2Spidy\DB\GemToGoldRateQuery;
use GW2Spidy\DB\ItemQuery;
use GW2Spidy\DB\ItemTypeQuery;
use GW2Spidy\DB\SellListingQuery;
use GW2Spidy\DB\WorkerQueueItemQuery;
use GW2Spidy\DB\ItemPeer;
use GW2Spidy\DB\BuyListingPeer;
use GW2Spidy\DB\SellListingPeer;
use GW2Spidy\DB\BuyListingQuery;

use GW2Spidy\Util\Functions;

/**
 * ----------------------
 *  route /search POST
 * ----------------------
 */
$app->post("/search", function (Request $request) use ($app) {
    // redirect to the GET with the search in the URL
    return $app->redirect($app['url_generator']->generate('search', array('search' => $request->get('search'), 'recipes' => $request->get('recipes', false))));
})
->bind('searchpost');

/**
 * ----------------------
 *  route /search GET
 * ----------------------
 */
$app->get("/search/{search}/{page}", function(Request $request, $search, $page) use($app) {
    if (!$search) {
        return $app->handle(Request::create("/searchform", 'GET'), HttpKernelInterface::SUB_REQUEST);
    }

    $con = \Propel::getConnection();

    $recipes = (bool)$request->get('recipes', false);
    $page = $page > 0 ? $page : 1;

    if ($recipes) {
        $table = "recipe";
        $route = "recipe";
        $q = RecipeQuery::create();
    } else {
        $table = "item";
        $route = "item";
        $q = ItemQuery::create();
    }

    $quoted = $con->quote($search);
    $q->withColumn("match(name, tp_name) AGAINST ({$quoted})", "relevance");
    $q->orderBy("relevance", \Criteria::DESC);

    $stmt = $con->prepare("SELECT MAX(MATCH(name, tp_name) AGAINST ({$quoted})) as max FROM {$table}");
    $stmt->execute();
    $max = $stmt->fetch(\PDO::FETCH_ASSOC);
    $max = $max['max'];
    $thres = $max * 0.9;

    $q->where("match(name, tp_name) AGAINST ({$quoted}) > {$thres}");

    if ($page == 1 && $q->count() == 1) {
        $onlyOne = $q->findOne();
        return $app->redirect($app['url_generator']->generate($route, array('dataId' => $onlyOne->getDataId())));
    }

    if ($recipes) {
        $content = recipe_list($app, $request, $q, $page, 25, array('search' => $search, 'type'=>'search', 'included' => true));
    } else {
        $content = item_list($app, $request, $q, $page, 25, array('search' => $search, 'type'=>'search', 'included' => true));
    }

    // use generic function to render
    return $app['twig']->render('search.html.twig', array(
        'recipes' => $recipes,
        'content' => $content,
        'search'  => $search,
    ));
})
->assert('search',   '[^/]*')
->assert('page',     '-?\d*')
->convert('page',    $toInt)
->convert('search',  function($search) { return urldecode($search); })
->value('search',    null)
->value('page',      1)
->bind('search');

/**
 * ----------------------
 *  route /searchform
 * ----------------------
 */
$app->get("/searchform", function() use($app) {
    return $app['twig']->render('search.html.twig', array('content' => '', 'search' => '', 'recipes' => false));
})
->bind('searchform');

