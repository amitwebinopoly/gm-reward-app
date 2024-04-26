<?php

/***********************************************************************************************************************
* This file is auto-generated. If you have an issue, please create a GitHub issue.                                     *
***********************************************************************************************************************/

declare(strict_types=1);

namespace ShopifyTest\Rest;

use Shopify\Auth\Session;
use Shopify\Context;
use Shopify\Rest\Admin2022_07\ProductResourceFeedback;
use ShopifyTest\BaseTestCase;
use ShopifyTest\Clients\MockRequest;

final class ProductResourceFeedback202207Test extends BaseTestCase
{
    /** @var Session */
    private $test_session;

    public function setUp(): void
    {
        parent::setUp();

        Context::$API_VERSION = "2022-07";

        $this->test_session = new Session("session_id", "test-shop.myshopify.io", true, "1234");
        $this->test_session->setAccessToken("this_is_a_test_token");
    }

    /**

     *
     * @return void
     */
    public function test_1(): void
    {
        $this->mockTransportRequests([
            new MockRequest(
                $this->buildMockHttpResponse(200, json_encode(
                  ["resource_feedback" => ["created_at" => "2023-07-13T01:41:42-04:00", "updated_at" => "2023-07-13T01:41:42-04:00", "resource_id" => 632910392, "resource_type" => "Product", "resource_updated_at" => "2023-07-13T01:41:06-04:00", "messages" => ["Needs at least one image."], "feedback_generated_at" => "2023-07-13T01:41:41-04:00", "state" => "requires_action"]]
                )),
                "https://test-shop.myshopify.io/admin/api/2022-07/products/632910392/resource_feedback.json",
                "POST",
                null,
                [
                    "X-Shopify-Access-Token: this_is_a_test_token",
                ],
                json_encode(["resource_feedback" => ["state" => "requires_action", "messages" => ["Needs at least one image."], "resource_updated_at" => "2023-07-13T01:41:06-04:00", "feedback_generated_at" => "2023-07-13T05:41:41.751444Z"]]),
            ),
        ]);

        $product_resource_feedback = new ProductResourceFeedback($this->test_session);
        $product_resource_feedback->product_id = 632910392;
        $product_resource_feedback->state = "requires_action";
        $product_resource_feedback->messages = [
            "Needs at least one image."
        ];
        $product_resource_feedback->resource_updated_at = "2023-07-13T01:41:06-04:00";
        $product_resource_feedback->feedback_generated_at = "2023-07-13T05:41:41.751444Z";
        $product_resource_feedback->save();
    }

    /**

     *
     * @return void
     */
    public function test_2(): void
    {
        $this->mockTransportRequests([
            new MockRequest(
                $this->buildMockHttpResponse(200, json_encode(
                  ["resource_feedback" => ["created_at" => "2023-07-13T01:41:43-04:00", "updated_at" => "2023-07-13T01:41:43-04:00", "resource_id" => 632910392, "resource_type" => "Product", "resource_updated_at" => "2023-07-13T01:41:06-04:00", "messages" => [], "feedback_generated_at" => "2023-07-13T01:41:42-04:00", "state" => "success"]]
                )),
                "https://test-shop.myshopify.io/admin/api/2022-07/products/632910392/resource_feedback.json",
                "POST",
                null,
                [
                    "X-Shopify-Access-Token: this_is_a_test_token",
                ],
                json_encode(["resource_feedback" => ["state" => "success", "resource_updated_at" => "2023-07-13T01:41:06-04:00", "feedback_generated_at" => "2023-07-13T05:41:42.451572Z"]]),
            ),
        ]);

        $product_resource_feedback = new ProductResourceFeedback($this->test_session);
        $product_resource_feedback->product_id = 632910392;
        $product_resource_feedback->state = "success";
        $product_resource_feedback->resource_updated_at = "2023-07-13T01:41:06-04:00";
        $product_resource_feedback->feedback_generated_at = "2023-07-13T05:41:42.451572Z";
        $product_resource_feedback->save();
    }

    /**

     *
     * @return void
     */
    public function test_3(): void
    {
        $this->mockTransportRequests([
            new MockRequest(
                $this->buildMockHttpResponse(200, json_encode(
                  ["resource_feedback" => [["created_at" => "2023-07-13T01:41:39-04:00", "updated_at" => "2023-07-13T01:41:39-04:00", "resource_id" => 632910392, "resource_type" => "Product", "resource_updated_at" => "2023-07-13T01:41:06-04:00", "messages" => ["Needs at least one image."], "feedback_generated_at" => "2023-07-13T00:41:39-04:00", "state" => "requires_action"]]]
                )),
                "https://test-shop.myshopify.io/admin/api/2022-07/products/632910392/resource_feedback.json",
                "GET",
                null,
                [
                    "X-Shopify-Access-Token: this_is_a_test_token",
                ],
            ),
        ]);

        ProductResourceFeedback::all(
            $this->test_session,
            ["product_id" => "632910392"],
            [],
        );
    }

}