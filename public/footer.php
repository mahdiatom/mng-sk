<?php 
// اضافه کردن فوتر
add_action('wp_footer', 'custom_footer_output');
function custom_footer_output() {

    ?>
    
    <footer class="custom-footer" >
        <p>&copy; <?php echo date('Y'); ?> تمامی حقوق برای سیستم هوشمند باشگاه اتم کلاب محفوظ است.</p>
        <p>طراحی شده توسط اتم کلاب</p>
    </footer>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const header = document.querySelector('.custom-header');
    const headerHeight = header.offsetHeight;
window.addEventListener('scroll', function () {
        if (window.scrollY > headerHeight) {
            header.classList.add('fixed');
        } else {
            header.classList.remove('fixed');
        }
    });
});



</script>

<?php 
    if(is_shop() || is_product_category() || is_product_tag() || is_search()){
    

    
?>

<script name="zaaaa">
jQuery(document).ready(function($) {

        
    // فیلتر زنده با AJAX
    function applyFilter() {
        const $search = $('#filter-search').val();
        const $category = $('#filter-category').val();
        const $tag = $('#filter-tag').val();
// ساخت URL جدید
        const url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('product_cat');
        url.searchParams.delete('product_tag');
if ($search) url.searchParams.set('s', $search);
        if ($category) url.searchParams.set('product_cat', $category);
        if ($tag) url.searchParams.set('product_tag', $tag);
window.history.pushState({}, '', url.toString());
// ارسال درخواست AJAX
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
                
                $('.products').html(response.data.html).show();
                $('p.woocommerce-result-count').html(response.data.count);
                window.history.pushState({}, '', url.toString());
                $('html, body').animate({ scrollTop: 0 }, 300);
            },
            error: function() {
                alert('خطا در بارگذاری محصولات. لطفاً دوباره تلاش کنید.');
            }
        });
    }
    $(document).on('click', '#button_filter_custom' , function(){
            applyFilter();
    } );

});

    
</script>


    <?php
    }
}

