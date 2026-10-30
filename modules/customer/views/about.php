<?php
/**
 * =========================================================
 * AU-AgroSale — Customer About Page
 * modules/customer/views/about.php
 * =========================================================
 * Rendered inside templates/customer.php (customer layout).
 * All markup is inside .customer-app (applied by layout).
 *
 * Variables available from controller:
 * @var array   $shops      Active shops from DB
 * @var \AAM\Core\View $view
 * @var string  $appUrl
 */
declare(strict_types=1);

$shops     = $shops ?? [];
$pageTitle = $pageTitle ?? 'About AU-AgroSale';

// Hero image (same path used by home page hero)
$heroBgImage  = $view->url('public/assets/customer/images/placeholders/hero.png');
$heroBgExists = file_exists(PUBLIC_PATH . '/assets/customer/images/placeholders/hero.png');

// Active nav highlight is set in customer.php layout via REQUEST_URI check
?>

<?php $view->section('styles'); ?>
<?php $view->endSection(); ?>

<!-- ══════════════════════════════════════════════════════════
     ABOUT PAGE HERO
     ══════════════════════════════════════════════════════════ -->
<section class="ca-about-hero" aria-labelledby="aboutHeroHeading">

    <?php if ($heroBgExists): ?>
    <div class="ca-about-hero__bg" aria-hidden="true">
        <img src="<?= $heroBgImage ?>"
             alt=""
             loading="eager"
             fetchpriority="high"
             decoding="sync"
             width="1440"
             height="580">
    </div>
    <?php else: ?>
    <div class="ca-about-hero__bg ca-about-hero__bg--gradient" aria-hidden="true">
        <div class="ca-about-hero__bg-art"></div>
    </div>
    <?php endif; ?>

    <div class="c-container ca-about-hero__inner">
        <!-- LEFT: text content -->
        <div class="ca-about-hero__content">
            <span class="ca-about-hero__eyebrow">
                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                About AU-AgroSale
            </span>

            <h1 class="ca-about-hero__title" id="aboutHeroHeading">
                Fresh From Arsi University Farms,<br>
                <span class="ca-about-hero__title-accent">Closer to Your Table.</span>
            </h1>

            <p class="ca-about-hero__subtitle">
                AU-AgroSale connects customers with quality agricultural products
                produced through Arsi University farms, making them available
                through participating university shops. Discover fresh dairy,
                grains, eggs, honey, meat, and more — conveniently ordered online
                and picked up from the shop of your choice.
            </p>

            <div class="ca-about-hero__ctas">
                <a href="<?= $view->url('customer/products') ?>"
                   class="ca-about-hero__btn-primary">
                    <i class="bi bi-grid-fill" aria-hidden="true"></i>
                    Browse Products
                </a>
                <a href="#caAboutShops"
                   class="ca-about-hero__btn-secondary">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    View Shops
                </a>
            </div>
        </div>

        <!-- RIGHT: illustrated visual (when no image, shows farm grid) -->
        <div class="ca-about-hero__visual" aria-hidden="true">
            <?php if ($heroBgExists): ?>
            <!-- Background image fills the hero; right side shows through -->
            <?php else: ?>
            <div class="ca-about-hero__illustration">
                <div class="ca-about-hero__ill-inner">
                    <div class="ca-about-hero__ill-grid">
                        <div class="ca-about-hero__ill-item">🥛<span>Fresh Milk</span></div>
                        <div class="ca-about-hero__ill-item">🌾<span>Grains</span></div>
                        <div class="ca-about-hero__ill-item">🥚<span>Farm Eggs</span></div>
                        <div class="ca-about-hero__ill-item">🍯<span>Honey</span></div>
                        <div class="ca-about-hero__ill-item">🥩<span>Meat</span></div>
                        <div class="ca-about-hero__ill-item">🥦<span>Vegetables</span></div>
                    </div>
                    <div class="ca-about-hero__ill-badge">
                        <i class="bi bi-award-fill" style="font-size:28px;color:#a5d6a7;"></i>
                        <div>
                            <b style="font-size:1.4rem;font-weight:800;color:#fff;display:block;line-height:1;">University</b>
                            <span style="font-size:12px;color:rgba(255,255,255,.75);">Backed Quality</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     TRUST / VALUE STATISTICS
     ══════════════════════════════════════════════════════════ -->
<section class="ca-about-trust" aria-labelledby="aboutTrustHeading">
    <h2 id="aboutTrustHeading" class="c-sr-only">Why Trust AU-AgroSale</h2>
    <div class="c-container">
        <div class="ca-about-trust__grid" role="list">

            <div class="ca-about-trust__item" role="listitem">
                <div class="ca-about-trust__icon" aria-hidden="true">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div class="ca-about-trust__label">Farm Fresh</div>
                <div class="ca-about-trust__sub">Quality agricultural products</div>
            </div>

            <div class="ca-about-trust__item" role="listitem">
                <div class="ca-about-trust__icon" aria-hidden="true">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div class="ca-about-trust__label">University Trusted</div>
                <div class="ca-about-trust__sub">Products from Arsi University farms</div>
            </div>

            <div class="ca-about-trust__item" role="listitem">
                <div class="ca-about-trust__icon" aria-hidden="true">
                    <i class="bi bi-shop-window"></i>
                </div>
                <div class="ca-about-trust__label">Multiple Shops</div>
                <div class="ca-about-trust__sub">Convenient pickup locations</div>
            </div>

            <div class="ca-about-trust__item" role="listitem">
                <div class="ca-about-trust__icon" aria-hidden="true">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="ca-about-trust__label">Quality Assured</div>
                <div class="ca-about-trust__sub">Fresh and carefully managed products</div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     OUR STORY
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-bg-alt" aria-labelledby="aboutStoryHeading">
    <div class="c-container">
        <div class="ca-about-story">

            <!-- Text side -->
            <div class="ca-about-story__content">
                <div class="c-section-header">
                    <span class="c-section-label">
                        <i class="bi bi-book-fill" aria-hidden="true"></i>
                        Background
                    </span>
                    <h2 class="c-section-title" id="aboutStoryHeading">Our Story</h2>
                    <p class="c-section-subtitle">
                        AU-AgroSale is designed to connect Arsi University agricultural
                        production with customers through a simple digital shopping experience.
                        The platform makes it easier for customers to discover, select, order,
                        and collect fresh agricultural products from participating university shops.
                    </p>
                    <p class="c-section-subtitle" style="margin-top:var(--c-space-3);">
                        Rooted in Arsi University's commitment to agricultural excellence and
                        community service, AU-AgroSale transforms farm-fresh produce into a
                        convenient, transparent, and accessible shopping experience.
                    </p>
                </div>
            </div>

            <!-- Journey visual -->
            <div class="ca-about-story__journey" aria-label="Farm to customer journey">
                <div class="ca-about-story__step">
                    <div class="ca-about-story__step-icon" aria-hidden="true">
                        <i class="bi bi-tree-fill"></i>
                    </div>
                    <div class="ca-about-story__step-body">
                        <div class="ca-about-story__step-title">University Farms</div>
                        <div class="ca-about-story__step-desc">Fresh production from Arsi University agricultural activities</div>
                    </div>
                </div>

                <div class="ca-about-story__connector" aria-hidden="true">
                    <i class="bi bi-arrow-down"></i>
                </div>

                <div class="ca-about-story__step">
                    <div class="ca-about-story__step-icon" aria-hidden="true">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <div class="ca-about-story__step-body">
                        <div class="ca-about-story__step-title">Quality Products</div>
                        <div class="ca-about-story__step-desc">Dairy, grains, eggs, honey, meat, vegetables and more</div>
                    </div>
                </div>

                <div class="ca-about-story__connector" aria-hidden="true">
                    <i class="bi bi-arrow-down"></i>
                </div>

                <div class="ca-about-story__step">
                    <div class="ca-about-story__step-icon" aria-hidden="true">
                        <i class="bi bi-shop-window"></i>
                    </div>
                    <div class="ca-about-story__step-body">
                        <div class="ca-about-story__step-title">University Shops</div>
                        <div class="ca-about-story__step-desc">Participating university retail shops across campus</div>
                    </div>
                </div>

                <div class="ca-about-story__connector" aria-hidden="true">
                    <i class="bi bi-arrow-down"></i>
                </div>

                <div class="ca-about-story__step ca-about-story__step--highlight">
                    <div class="ca-about-story__step-icon" aria-hidden="true">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="ca-about-story__step-body">
                        <div class="ca-about-story__step-title">You</div>
                        <div class="ca-about-story__step-desc">Fresh products delivered conveniently to your chosen shop</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     WHAT WE OFFER — Product Categories
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-surface" aria-labelledby="aboutOfferHeading">
    <div class="c-container">
        <div class="c-section-header c-section-header--center">
            <span class="c-section-label">Our Products</span>
            <h2 class="c-section-title" id="aboutOfferHeading">What You Can Find</h2>
            <p class="c-section-subtitle">
                Explore a wide range of fresh agricultural products sourced from
                Arsi University farms and made available through our participating shops.
            </p>
        </div>

        <div class="ca-about-offer__grid" role="list" aria-label="Product categories">

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🥛</div>
                <div class="ca-about-offer__card-name">Dairy</div>
                <div class="ca-about-offer__card-desc">Fresh milk, yogurt, and dairy products from university herds</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🌾</div>
                <div class="ca-about-offer__card-name">Grains &amp; Cereals</div>
                <div class="ca-about-offer__card-desc">Teff, wheat, barley, and cereals grown on university farms</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🥚</div>
                <div class="ca-about-offer__card-name">Eggs</div>
                <div class="ca-about-offer__card-desc">Farm-fresh eggs from university poultry production</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🍯</div>
                <div class="ca-about-offer__card-name">Honey</div>
                <div class="ca-about-offer__card-desc">Pure natural honey from university apiculture activities</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🥩</div>
                <div class="ca-about-offer__card-name">Meat</div>
                <div class="ca-about-offer__card-desc">Quality meat products from university livestock activities</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🥦</div>
                <div class="ca-about-offer__card-name">Vegetables</div>
                <div class="ca-about-offer__card-desc">Seasonal vegetables grown in university agricultural fields</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🍎</div>
                <div class="ca-about-offer__card-name">Fruits</div>
                <div class="ca-about-offer__card-desc">Fresh fruits harvested from university orchards and gardens</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">🫙</div>
                <div class="ca-about-offer__card-name">Oils</div>
                <div class="ca-about-offer__card-desc">Cooking oils and extracts from university oil-seed production</div>
            </div>

            <div class="ca-about-offer__card" role="listitem">
                <div class="ca-about-offer__card-icon" aria-hidden="true">📦</div>
                <div class="ca-about-offer__card-name">Other Products</div>
                <div class="ca-about-offer__card-desc">Additional agricultural products from university production activities</div>
            </div>

        </div>

        <div class="ca-about-offer__cta">
            <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-primary c-btn-lg">
                <i class="bi bi-grid-fill" aria-hidden="true"></i>
                Browse All Products
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     WHY AU-AGROSALE
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-bg-alt" aria-labelledby="aboutWhyHeading">
    <div class="c-container">
        <div class="c-section-header c-section-header--center">
            <span class="c-section-label">Our Values</span>
            <h2 class="c-section-title" id="aboutWhyHeading">Why Shop With AU-AgroSale?</h2>
            <p class="c-section-subtitle">
                A marketplace built on quality, trust, and convenience — straight from
                Arsi University to your table.
            </p>
        </div>

        <div class="ca-about-why__grid" role="list">

            <div class="ca-about-why__card" role="listitem">
                <div class="ca-about-why__card-icon ca-about-why__card-icon--green" aria-hidden="true">
                    <i class="bi bi-tree-fill"></i>
                </div>
                <h3 class="ca-about-why__card-title">Farm Fresh</h3>
                <p class="ca-about-why__card-desc">
                    Products sourced directly from Arsi University agricultural activities,
                    ensuring freshness and quality at every step.
                </p>
            </div>

            <div class="ca-about-why__card" role="listitem">
                <div class="ca-about-why__card-icon ca-about-why__card-icon--amber" aria-hidden="true">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <h3 class="ca-about-why__card-title">Quality Assured</h3>
                <p class="ca-about-why__card-desc">
                    Products are managed through the university's agricultural and retail
                    operations, maintaining consistent quality standards.
                </p>
            </div>

            <div class="ca-about-why__card" role="listitem">
                <div class="ca-about-why__card-icon ca-about-why__card-icon--blue" aria-hidden="true">
                    <i class="bi bi-shop-window"></i>
                </div>
                <h3 class="ca-about-why__card-title">Convenient Pickup</h3>
                <p class="ca-about-why__card-desc">
                    Choose your preferred participating university shop and collect your
                    order at a time that suits you.
                </p>
            </div>

            <div class="ca-about-why__card" role="listitem">
                <div class="ca-about-why__card-icon ca-about-why__card-icon--green" aria-hidden="true">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <h3 class="ca-about-why__card-title">Secure Shopping</h3>
                <p class="ca-about-why__card-desc">
                    Your customer account and order information are protected with
                    secure authentication and privacy practices.
                </p>
            </div>

            <div class="ca-about-why__card" role="listitem">
                <div class="ca-about-why__card-icon ca-about-why__card-icon--amber" aria-hidden="true">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
                <h3 class="ca-about-why__card-title">Simple Ordering</h3>
                <p class="ca-about-why__card-desc">
                    Browse products, add them to your cart, checkout in a few steps,
                    and track the status of your orders with ease.
                </p>
            </div>

            <div class="ca-about-why__card" role="listitem">
                <div class="ca-about-why__card-icon ca-about-why__card-icon--blue" aria-hidden="true">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h3 class="ca-about-why__card-title">University Connected</h3>
                <p class="ca-about-why__card-desc">
                    A digital marketplace directly connected to Arsi University agricultural
                    production and retail activities — transparent and accountable.
                </p>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     HOW IT WORKS
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-surface" aria-labelledby="aboutHowHeading">
    <div class="c-container">
        <div class="c-section-header c-section-header--center">
            <span class="c-section-label">Simple Process</span>
            <h2 class="c-section-title" id="aboutHowHeading">How AU-AgroSale Works</h2>
            <p class="c-section-subtitle">
                From browsing to pickup — a clear, simple process designed for your convenience.
            </p>
        </div>

        <div class="ca-about-how__grid" role="list" aria-label="How to shop steps">

            <div class="ca-about-how__step" role="listitem">
                <div class="ca-about-how__step-num" aria-hidden="true">01</div>
                <div class="ca-about-how__step-icon" aria-hidden="true">
                    <i class="bi bi-grid-fill"></i>
                </div>
                <h3 class="ca-about-how__step-title">Browse</h3>
                <p class="ca-about-how__step-desc">
                    Explore available agricultural products from Arsi University farms.
                </p>
            </div>

            <div class="ca-about-how__connector" aria-hidden="true">
                <i class="bi bi-arrow-right"></i>
            </div>

            <div class="ca-about-how__step" role="listitem">
                <div class="ca-about-how__step-num" aria-hidden="true">02</div>
                <div class="ca-about-how__step-icon" aria-hidden="true">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <h3 class="ca-about-how__step-title">Choose Your Shop</h3>
                <p class="ca-about-how__step-desc">
                    Select the participating university shop where you want to collect your order.
                </p>
            </div>

            <div class="ca-about-how__connector" aria-hidden="true">
                <i class="bi bi-arrow-right"></i>
            </div>

            <div class="ca-about-how__step" role="listitem">
                <div class="ca-about-how__step-num" aria-hidden="true">03</div>
                <div class="ca-about-how__step-icon" aria-hidden="true">
                    <i class="bi bi-cart-plus-fill"></i>
                </div>
                <h3 class="ca-about-how__step-title">Add to Cart</h3>
                <p class="ca-about-how__step-desc">
                    Select the products and quantities you need and add them to your cart.
                </p>
            </div>

            <div class="ca-about-how__connector" aria-hidden="true">
                <i class="bi bi-arrow-right"></i>
            </div>

            <div class="ca-about-how__step" role="listitem">
                <div class="ca-about-how__step-num" aria-hidden="true">04</div>
                <div class="ca-about-how__step-icon" aria-hidden="true">
                    <i class="bi bi-credit-card-2-front-fill"></i>
                </div>
                <h3 class="ca-about-how__step-title">Checkout</h3>
                <p class="ca-about-how__step-desc">
                    Confirm your order and complete the payment process securely.
                </p>
            </div>

            <div class="ca-about-how__connector" aria-hidden="true">
                <i class="bi bi-arrow-right"></i>
            </div>

            <div class="ca-about-how__step" role="listitem">
                <div class="ca-about-how__step-num" aria-hidden="true">05</div>
                <div class="ca-about-how__step-icon" aria-hidden="true">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
                <h3 class="ca-about-how__step-title">Pickup</h3>
                <p class="ca-about-how__step-desc">
                    Collect your fresh order from your selected university shop.
                </p>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     OUR SHOPS — Dynamic from DB
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-bg-alt" id="caAboutShops" aria-labelledby="aboutShopsHeading">
    <div class="c-container">
        <div class="c-section-header c-section-header--row">
            <div>
                <span class="c-section-label">Shop Locations</span>
                <h2 class="c-section-title" id="aboutShopsHeading">Shop From a Location Near You</h2>
            </div>
            <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary" aria-label="View all shops">
                <i class="bi bi-shop" aria-hidden="true"></i>
                View All Shops
            </a>
        </div>

        <?php if (!empty($shops)): ?>
        <div class="ca-about-shops__grid" role="list" aria-label="Participating shops">
            <?php foreach ($shops as $shop):
                $shopId    = (int)$shop['id'];
                $shopName  = $shop['shop_name'] ?? $shop['name'] ?? 'Shop';
                $location  = $shop['location'] ?? '';
                $prodCount = (int)($shop['product_count'] ?? 0);
                $shopUrl   = $view->url('customer/shops/' . $shopId . '/availability');
                $shopImg   = \AAM\Helpers\UploadHelper::shopImageUrl($shop['shop_image'] ?? null);
                $isActive  = (bool)($shop['is_active'] ?? true);
            ?>
            <div class="ca-about-shops__card" role="listitem" aria-label="<?= e($shopName) ?>">

                <!-- Shop image -->
                <div class="ca-about-shops__card-img" aria-hidden="true">
                    <?php if ($shopImg): ?>
                    <img src="<?= e($shopImg) ?>"
                         alt="<?= e($shopName) ?>"
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="ca-about-shops__card-img-placeholder" style="display:none;">
                        <i class="bi bi-building"></i>
                    </div>
                    <?php else: ?>
                    <div class="ca-about-shops__card-img-placeholder">
                        <i class="bi bi-building"></i>
                    </div>
                    <?php endif; ?>

                    <!-- Status dot -->
                    <span class="ca-about-shops__status-dot <?= $isActive ? 'ca-about-shops__status-dot--active' : '' ?>"
                          title="<?= $isActive ? 'Active' : 'Inactive' ?>"
                          aria-label="<?= $isActive ? 'Shop is active' : 'Shop is inactive' ?>"></span>
                </div>

                <!-- Shop body -->
                <div class="ca-about-shops__card-body">
                    <h3 class="ca-about-shops__card-name"><?= e($shopName) ?></h3>

                    <?php if ($location): ?>
                    <div class="ca-about-shops__card-location">
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        <?= e($location) ?>
                    </div>
                    <?php endif; ?>

                    <div class="ca-about-shops__card-meta">
                        <span class="ca-about-shops__card-count">
                            <i class="bi bi-box-seam" aria-hidden="true"></i>
                            <?= $prodCount > 0 ? $prodCount . ' products available' : 'View products' ?>
                        </span>
                        <span class="ca-about-shops__card-status <?= $isActive ? 'ca-about-shops__card-status--active' : '' ?>">
                            <?= $isActive ? 'Open' : 'Closed' ?>
                        </span>
                    </div>

                    <a href="<?= $shopUrl ?>"
                       class="ca-about-shops__card-btn c-btn c-btn-secondary"
                       aria-label="View products at <?= e($shopName) ?>">
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        View Products
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="customer-empty-state">
            <div class="customer-empty-state__icon" aria-hidden="true">
                <i class="bi bi-shop"></i>
            </div>
            <h3 class="customer-empty-state__title">Shops Coming Soon</h3>
            <p class="customer-empty-state__desc">
                Our participating shops will be listed here once they are active.
            </p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     UNIVERSITY CONNECTION
     ══════════════════════════════════════════════════════════ -->
<section class="ca-about-university c-section" aria-labelledby="aboutUniversityHeading">
    <div class="c-container">
        <div class="ca-about-university__inner">

            <div class="ca-about-university__content">
                <span class="c-section-label">
                    <i class="bi bi-building-fill" aria-hidden="true"></i>
                    Our Institution
                </span>
                <h2 class="c-section-title" id="aboutUniversityHeading">
                    Powered by Arsi University
                </h2>
                <p class="c-section-subtitle">
                    AU-AgroSale is the official digital marketplace of Arsi University,
                    supporting the university's mission to connect agricultural production
                    with the community through accessible technology.
                </p>
                <ul class="ca-about-university__list" role="list">
                    <li class="ca-about-university__list-item">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        Agricultural production from Arsi University farms and fields
                    </li>
                    <li class="ca-about-university__list-item">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        Digital transformation of university agricultural retail operations
                    </li>
                    <li class="ca-about-university__list-item">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        Quality products managed through university oversight
                    </li>
                    <li class="ca-about-university__list-item">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        Community-oriented service and convenient product access
                    </li>
                    <li class="ca-about-university__list-item">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        Transparent and accountable marketplace operations
                    </li>
                </ul>
            </div>

            <div class="ca-about-university__visual" aria-hidden="true">
                <div class="ca-about-university__emblem">
                    <div class="ca-about-university__emblem-icon">🎓</div>
                    <div class="ca-about-university__emblem-name">Arsi University</div>
                    <div class="ca-about-university__emblem-sub">Agricultural Marketplace</div>
                    <div class="ca-about-university__emblem-divider"></div>
                    <div class="ca-about-university__emblem-brand">
                        <span class="ca-about-university__emblem-leaf">🌿</span>
                        <span>AU-AgroSale</span>
                    </div>
                    <div class="ca-about-university__emblem-tagline">
                        From Our Farms to Your Table
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FROM OUR FARMS TO YOUR TABLE — CTA section
     ══════════════════════════════════════════════════════════ -->
<section class="ca-about-farms-cta c-section c-section-surface" aria-labelledby="aboutFarmsHeading">
    <div class="c-container ca-about-farms-cta__inner">
        <div class="ca-about-farms-cta__content">
            <span class="ca-about-farms-cta__eyebrow" aria-hidden="true">🌱</span>
            <h2 class="ca-about-farms-cta__title" id="aboutFarmsHeading">
                From Our Farms to Your Table
            </h2>
            <p class="ca-about-farms-cta__desc">
                Discover quality agricultural products connected to Arsi University
                and enjoy a simple, convenient way to shop and pick up from your
                selected university shop.
            </p>
            <a href="<?= $view->url('customer/products') ?>"
               class="c-btn c-btn-primary c-btn-lg ca-about-farms-cta__btn">
                <i class="bi bi-grid-fill" aria-hidden="true"></i>
                Browse Products
            </a>
        </div>

        <div class="ca-about-farms-cta__visual" aria-hidden="true">
            <div class="ca-about-farms-cta__produce-grid">
                <span>🥛</span><span>🌾</span><span>🥚</span>
                <span>🍯</span><span>🥩</span><span>🥦</span>
                <span>🍎</span><span>🫙</span><span>📦</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FAQ
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-bg-alt" aria-labelledby="aboutFaqHeading">
    <div class="c-container">
        <div class="c-section-header c-section-header--center">
            <span class="c-section-label">Questions</span>
            <h2 class="c-section-title" id="aboutFaqHeading">Frequently Asked Questions</h2>
            <p class="c-section-subtitle">
                Everything you need to know about shopping with AU-AgroSale.
            </p>
        </div>

        <div class="ca-about-faq" role="list" aria-label="Frequently asked questions">

            <div class="ca-about-faq__item" role="listitem">
                <button class="ca-about-faq__question"
                        aria-expanded="false"
                        aria-controls="faq-1"
                        id="faq-btn-1"
                        type="button">
                    <span>What is AU-AgroSale?</span>
                    <i class="bi bi-plus-lg ca-about-faq__icon" aria-hidden="true"></i>
                </button>
                <div class="ca-about-faq__answer" id="faq-1" role="region" aria-labelledby="faq-btn-1" hidden>
                    <p>
                        AU-AgroSale is the official digital marketplace of Arsi University.
                        It connects customers with fresh agricultural products produced through
                        university farms and makes them available through participating university
                        retail shops.
                    </p>
                </div>
            </div>

            <div class="ca-about-faq__item" role="listitem">
                <button class="ca-about-faq__question"
                        aria-expanded="false"
                        aria-controls="faq-2"
                        id="faq-btn-2"
                        type="button">
                    <span>Where do the products come from?</span>
                    <i class="bi bi-plus-lg ca-about-faq__icon" aria-hidden="true"></i>
                </button>
                <div class="ca-about-faq__answer" id="faq-2" role="region" aria-labelledby="faq-btn-2" hidden>
                    <p>
                        All products on AU-AgroSale come from Arsi University's agricultural
                        activities, including dairy farms, crop fields, poultry, beekeeping,
                        and livestock operations. Products are managed through the university's
                        agricultural and retail operations before being made available on the platform.
                    </p>
                </div>
            </div>

            <div class="ca-about-faq__item" role="listitem">
                <button class="ca-about-faq__question"
                        aria-expanded="false"
                        aria-controls="faq-3"
                        id="faq-btn-3"
                        type="button">
                    <span>Can I choose a pickup shop?</span>
                    <i class="bi bi-plus-lg ca-about-faq__icon" aria-hidden="true"></i>
                </button>
                <div class="ca-about-faq__answer" id="faq-3" role="region" aria-labelledby="faq-btn-3" hidden>
                    <p>
                        Yes. When browsing products or at checkout, you can select the
                        participating university shop where you want to collect your order.
                        Product availability may vary by shop, so selecting your preferred
                        shop first will show you what is available there.
                    </p>
                </div>
            </div>

            <div class="ca-about-faq__item" role="listitem">
                <button class="ca-about-faq__question"
                        aria-expanded="false"
                        aria-controls="faq-4"
                        id="faq-btn-4"
                        type="button">
                    <span>Do you offer delivery?</span>
                    <i class="bi bi-plus-lg ca-about-faq__icon" aria-hidden="true"></i>
                </button>
                <div class="ca-about-faq__answer" id="faq-4" role="region" aria-labelledby="faq-btn-4" hidden>
                    <p>
                        Currently, AU-AgroSale operates on a pickup-only model. After placing
                        your order, you collect it directly from your selected university shop.
                        This ensures product freshness and allows for direct interaction with
                        the shop team.
                    </p>
                </div>
            </div>

            <div class="ca-about-faq__item" role="listitem">
                <button class="ca-about-faq__question"
                        aria-expanded="false"
                        aria-controls="faq-5"
                        id="faq-btn-5"
                        type="button">
                    <span>How do I place an order?</span>
                    <i class="bi bi-plus-lg ca-about-faq__icon" aria-hidden="true"></i>
                </button>
                <div class="ca-about-faq__answer" id="faq-5" role="region" aria-labelledby="faq-btn-5" hidden>
                    <p>
                        Browse the product catalog, select a shop, add products to your cart,
                        and proceed to checkout. You will need a customer account to complete
                        your order. After checkout, your order will be prepared at the selected
                        shop and ready for pickup.
                    </p>
                </div>
            </div>

            <div class="ca-about-faq__item" role="listitem">
                <button class="ca-about-faq__question"
                        aria-expanded="false"
                        aria-controls="faq-6"
                        id="faq-btn-6"
                        type="button">
                    <span>How can I view my orders?</span>
                    <i class="bi bi-plus-lg ca-about-faq__icon" aria-hidden="true"></i>
                </button>
                <div class="ca-about-faq__answer" id="faq-6" role="region" aria-labelledby="faq-btn-6" hidden>
                    <p>
                        Sign in to your customer account and go to <strong>My Orders</strong>
                        from the navigation menu or your account center. You can view order
                        history, current order status, and download receipts for your purchases.
                    </p>
                </div>
            </div>

        </div><!-- /.ca-about-faq -->
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FINAL CTA — Ready to Shop Fresh?
     ══════════════════════════════════════════════════════════ -->
<section class="ca-about-final-cta" aria-labelledby="aboutFinalCtaHeading">
    <div class="c-container ca-about-final-cta__inner">
        <span class="ca-about-final-cta__eyebrow" aria-hidden="true">🌾</span>
        <h2 class="ca-about-final-cta__title" id="aboutFinalCtaHeading">
            Ready to Shop Fresh?
        </h2>
        <p class="ca-about-final-cta__desc">
            Explore agricultural products available from Arsi University shops.
        </p>
        <div class="ca-about-final-cta__buttons">
            <a href="<?= $view->url('customer/products') ?>"
               class="ca-about-final-cta__btn-primary">
                <i class="bi bi-grid-fill" aria-hidden="true"></i>
                Browse Products
            </a>
            <a href="<?= $view->url('customer/shops') ?>"
               class="ca-about-final-cta__btn-secondary">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                View Shops
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FOOTER — reusing the SAME customer footer as home.php
     ══════════════════════════════════════════════════════════ -->
<footer class="ch-footer" role="contentinfo">
    <div class="c-container">
        <div class="ch-footer__grid">

            <!-- Brand col -->
            <div class="ch-footer__brand-col">
                <a href="<?= $view->url('customer') ?>"
                   class="ch-footer__brand"
                   aria-label="AU-AgroSale home">
                    <div class="customer-navbar__logo" style="width:32px;height:32px;font-size:15px;" aria-hidden="true">🌿</div>
                    <div>
                        <div class="ch-footer__brand-name">AU-AgroSale</div>
                        <div class="ch-footer__brand-sub">Arsi University</div>
                    </div>
                </a>
                <p class="ch-footer__about">
                    AU-AgroSale is the official online platform for purchasing quality agricultural products from Arsi University farms.
                </p>
                <div class="ch-footer__social" role="list" aria-label="Social links">
                    <a href="#" class="ch-footer__social-link" aria-label="Twitter" role="listitem"><i class="bi bi-twitter-x" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="Instagram" role="listitem"><i class="bi bi-instagram" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="Telegram" role="listitem"><i class="bi bi-telegram" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="WhatsApp" role="listitem"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <nav class="ch-footer__nav-col" aria-label="Quick links">
                <div class="ch-footer__nav-heading">Quick Links</div>
                <ul class="ch-footer__nav-list">
                    <li><a href="<?= $view->url('customer') ?>">Home</a></li>
                    <li><a href="<?= $view->url('customer/products') ?>">Products</a></li>
                    <li><a href="<?= $view->url('customer/shops') ?>">Shops</a></li>
                    <li><a href="<?= $view->url('customer/orders') ?>">My Orders</a></li>
                    <li><a href="<?= $view->url('customer/cart') ?>">Cart</a></li>
                    <li><a href="<?= $view->url('customer/about') ?>" aria-current="page">About Us</a></li>
                </ul>
            </nav>

            <!-- Customer Service -->
            <nav class="ch-footer__nav-col" aria-label="Customer service">
                <div class="ch-footer__nav-heading">Customer Service</div>
                <ul class="ch-footer__nav-list">
                    <li><a href="<?= $view->url('customer/about') ?>#caAboutHow">How to Order</a></li>
                    <li><a href="<?= $view->url('customer/about') ?>#caAboutShops">Pickup Information</a></li>
                    <li><a href="<?= $view->url('customer/account/help') ?>">Help Center</a></li>
                </ul>
            </nav>

            <!-- Our Shops -->
            <nav class="ch-footer__nav-col" aria-label="Shop locations">
                <div class="ch-footer__nav-heading">Our Shops</div>
                <ul class="ch-footer__nav-list">
                    <?php if (!empty($shops)): ?>
                    <?php foreach (array_slice($shops, 0, 5) as $shop): ?>
                    <li>
                        <a href="<?= $view->url('customer/shops/' . (int)$shop['id'] . '/availability') ?>">
                            <?= e($shop['shop_name'] ?? $shop['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <li><a href="<?= $view->url('customer/shops') ?>">All Shop Locations</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Contact + Newsletter -->
            <div class="ch-footer__contact-col">
                <div class="ch-footer__nav-heading">Contact Us</div>
                <ul class="ch-footer__contact-list">
                    <li>
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        Arsi University, Asella<br>Oromia, Ethiopia
                    </li>
                    <li>
                        <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                        +251 911 234567
                    </li>
                    <li>
                        <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                        info@au-agrosale.edu.et
                    </li>
                    <li>
                        <i class="bi bi-clock-fill" aria-hidden="true"></i>
                        Mon &ndash; Sat: 8:00 AM &ndash; 6:00 PM
                    </li>
                </ul>

                <div class="ch-footer__newsletter" style="margin-top: var(--c-space-5);">
                    <div class="ch-footer__nav-heading">Stay Updated</div>
                    <p class="ch-footer__newsletter-desc">
                        Subscribe to get updates on new products and special offers.
                    </p>
                    <form class="ch-footer__subscribe-form"
                          action="#"
                          method="post"
                          onsubmit="return false;"
                          aria-label="Newsletter subscription">
                        <input type="email"
                               class="ch-footer__subscribe-input"
                               placeholder="Enter your email"
                               aria-label="Email address for newsletter">
                        <button type="submit" class="ch-footer__subscribe-btn">Subscribe</button>
                    </form>
                </div>
            </div>

        </div><!-- /.ch-footer__grid -->

        <div class="ch-footer__bottom">
            <p class="ch-footer__copyright">
                &copy; <?= date('Y') ?> AU-AgroSale. All rights reserved.
            </p>
            <div class="ch-footer__bottom-links">
                <a href="#">Privacy Policy</a>
                <span aria-hidden="true">·</span>
                <a href="#">Terms of Service</a>
                <span aria-hidden="true">·</span>
                <a href="#">Sitemap</a>
            </div>
        </div>
    </div>
</footer>

<?php $view->section('scripts'); ?>
<script>
/* ── About page: FAQ accordion (keyboard accessible) ── */
(function () {
    'use strict';

    var items = document.querySelectorAll('.ca-about-faq__item');

    items.forEach(function (item) {
        var btn    = item.querySelector('.ca-about-faq__question');
        var answer = item.querySelector('.ca-about-faq__answer');
        var icon   = item.querySelector('.ca-about-faq__icon');

        if (!btn || !answer) return;

        btn.addEventListener('click', function () {
            var isOpen = btn.getAttribute('aria-expanded') === 'true';

            // Close all other items
            items.forEach(function (other) {
                var ob = other.querySelector('.ca-about-faq__question');
                var oa = other.querySelector('.ca-about-faq__answer');
                var oi = other.querySelector('.ca-about-faq__icon');
                if (ob && oa && ob !== btn) {
                    ob.setAttribute('aria-expanded', 'false');
                    oa.hidden = true;
                    oa.style.maxHeight = '0';
                    if (oi) {
                        oi.className = 'bi bi-plus-lg ca-about-faq__icon';
                    }
                }
            });

            // Toggle this item
            if (isOpen) {
                btn.setAttribute('aria-expanded', 'false');
                answer.hidden = true;
                answer.style.maxHeight = '0';
                if (icon) icon.className = 'bi bi-plus-lg ca-about-faq__icon';
            } else {
                btn.setAttribute('aria-expanded', 'true');
                answer.hidden = false;
                answer.style.maxHeight = answer.scrollHeight + 'px';
                if (icon) icon.className = 'bi bi-dash-lg ca-about-faq__icon';
            }
        });

        // Keyboard: Enter / Space handled by button natively; arrow keys for bonus UX
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var nextItem = item.nextElementSibling;
                if (nextItem) {
                    var nextBtn = nextItem.querySelector('.ca-about-faq__question');
                    if (nextBtn) nextBtn.focus();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                var prevItem = item.previousElementSibling;
                if (prevItem) {
                    var prevBtn = prevItem.querySelector('.ca-about-faq__question');
                    if (prevBtn) prevBtn.focus();
                }
            }
        });
    });

    /* ── Smooth-scroll for in-page anchor links ── */
    document.querySelectorAll('a[href^="#ca"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var targetId = anchor.getAttribute('href').slice(1);
            var target   = document.getElementById(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}());
</script>
<?php $view->endSection(); ?>
