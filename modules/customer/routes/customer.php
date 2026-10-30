<?php
/**
 * Customer Module Routes — Phase 1 placeholder.
 *
 * All routes are listed here for planning purposes.
 * Actual route registration (Router::get/post calls) to be
 * implemented in a later phase once the routing API is confirmed.
 *
 * Planned routes:
 *
 * GET  /customer                         → HomeController::index
 * GET  /customer/products                → ProductController::index
 * GET  /customer/products/{id}           → ProductController::show
 * GET  /customer/products/search         → ProductController::search
 * GET  /customer/shops                   → ShopController::index
 * GET  /customer/shops/{id}/availability → ShopController::availability
 * GET  /customer/cart                    → CartController::index
 * POST /customer/cart/add                → CartController::add
 * POST /customer/cart/update             → CartController::update
 * POST /customer/cart/remove             → CartController::remove
 * GET  /customer/checkout                → CheckoutController::index
 * POST /customer/checkout/process        → CheckoutController::process
 * GET  /customer/checkout/payment        → PaymentController::index
 * POST /customer/checkout/payment/pay    → PaymentController::pay
 * GET  /customer/checkout/success        → CheckoutController::success
 * GET  /customer/orders                  → OrderController::index
 * GET  /customer/orders/{id}             → OrderController::show
 * GET  /customer/orders/{id}/receipt     → OrderController::receipt
 */
