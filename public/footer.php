<?php 

if ( ! defined('ABSPATH') ) exit;
// اضافه کردن فوتر
add_action('wp_footer', 'custom_footer_output');
function custom_footer_output() {
    $footer_line1 = sc_get_setting(
        'sc_footer_text_line1',
        '{year} تمامی حقوق برای سیستم هوشمند باشگاه اتم کلاب محفوظ است.'
    );
    $footer_line2 = sc_get_setting('sc_footer_text_line2', 'طراحی شده توسط اتم کلاب');
    $footer_line1 = str_replace('{year}', date('Y'), (string) $footer_line1);

    ?>
    
    <footer class="custom-footer" >
       <?php if (trim($footer_line1) !== '') : ?>
      <a href="https://atomclubapp.ir" target="_blank"> <p><?php echo esc_html($footer_line1); ?></p></a>
       <?php endif; ?>
       <?php if (trim($footer_line2) !== '') : ?>
       <a href="https://atomclubapp.ir" target="_blank"> <p><?php echo esc_html($footer_line2); ?></p></a>
       <?php endif; ?>
    </footer>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var header = document.querySelector('.custom-header.header_top') || document.querySelector('.custom-header');
    if (!header) {
        return;
    }

    var spacer = null;
    var ticking = false;
    var lastFixed = false;

    function measureHeaderHeight() {
        return Math.ceil(header.getBoundingClientRect().height);
    }

    function ensureSpacer(height) {
        if (!spacer) {
            spacer = document.createElement('div');
            spacer.className = 'sc-header-fixed-spacer';
            spacer.setAttribute('aria-hidden', 'true');
            if (header.nextSibling) {
                header.parentNode.insertBefore(spacer, header.nextSibling);
            } else {
                header.parentNode.appendChild(spacer);
            }
        }
        spacer.style.height = height + 'px';
        spacer.style.display = 'block';
    }

    function hideSpacer() {
        if (!spacer) {
            return;
        }
        spacer.style.display = 'none';
        spacer.style.height = '0px';
    }

    function updateFixedHeader() {
        ticking = false;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var headerH = lastFixed && spacer
            ? (parseFloat(spacer.style.height) || measureHeaderHeight())
            : measureHeaderHeight();

        // روی صفحات کوتاه، fixed کردن هدر ارتفاع سند را به‌هم می‌زند و فوتر می‌پرد
        var docH = Math.max(
            document.documentElement.scrollHeight,
            document.body ? document.body.scrollHeight : 0
        );
        var overflow = docH - window.innerHeight;
        var canStick = overflow > (headerH + 24);

        var shouldFix = canStick && scrollY > headerH;

        if (shouldFix === lastFixed) {
            return;
        }

        if (shouldFix) {
            var h = measureHeaderHeight();
            ensureSpacer(h);
            header.classList.add('fixed');
            lastFixed = true;
        } else {
            header.classList.remove('fixed');
            hideSpacer();
            lastFixed = false;
        }
    }

    function onScrollOrResize() {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(updateFixedHeader);
        }
    }

    window.addEventListener('scroll', onScrollOrResize, { passive: true });
    window.addEventListener('resize', onScrollOrResize, { passive: true });
    updateFixedHeader();
});
</script>

<?php 
    if(is_shop() || is_product_category() || is_product_tag() || is_search()){
    

    
?>

<script name="zaaaa">
jQuery(document).ready(function($) {
    const $products = $('.products');
    const $filterBtn = $('#button_filter_custom');
    const $clearBtn = $('#button_filter_clear');

    function hasActiveFilters() {
        return !!($('#filter-search').val() || $('#filter-category').val() || $('#filter-tag').val());
    }

    function toggleClearButton() {
        if (!$clearBtn.length) return;
        $clearBtn.toggleClass('is-hidden', !hasActiveFilters());
    }

    function setLoading(isLoading) {
        $filterBtn.toggleClass('is-loading', isLoading);
        $products.toggleClass('sc-shop-products-loading', isLoading);
    }

    function applyFilter() {
        const $search = $('#filter-search').val();
        const $category = $('#filter-category').val();
        const $tag = $('#filter-tag').val();

        const url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('product_cat');
        url.searchParams.delete('product_tag');

        if ($search) url.searchParams.set('s', $search);
        if ($category) url.searchParams.set('product_cat', $category);
        if ($tag) url.searchParams.set('product_tag', $tag);

        window.history.pushState({}, '', url.toString());
        setLoading(true);

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'GET',
            data: {
                action: 'filter_products_ajax',
                search: $search,
                category: $category,
                tag: $tag,
                nonce: ajax_object.nonce
            },
            success: function(response) {
                if (response && response.data) {
                    $products.html(response.data.html).show();
                    $('p.woocommerce-result-count').html(response.data.count);
                }
                toggleClearButton();
                $('html, body').animate({ scrollTop: $products.offset().top - 120 }, 300);
            },
            error: function() {
                alert('خطا در بارگذاری محصولات. لطفاً دوباره تلاش کنید.');
            },
            complete: function() {
                setLoading(false);
            }
        });
    }

    $(document).on('click', '#button_filter_custom', function(e) {
        e.preventDefault();
        applyFilter();
    });

    $(document).on('click', '#button_filter_clear', function(e) {
        e.preventDefault();
        $('#filter-search').val('');
        $('#filter-category').val('');
        $('#filter-tag').val('');
        applyFilter();
    });

    $('#filter-search').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyFilter();
        }
    });

    $('#filter-category, #filter-tag').on('change', toggleClearButton);
    $('#filter-search').on('input', toggleClearButton);

    toggleClearButton();
});
</script>


    <?php
    }
}

