<?php
global $wpdb;
$faq_table = $wpdb->prefix . 'sc_faq';
$faqs = $wpdb->get_results("SELECT * FROM $faq_table ORDER BY id ASC");

?>
<div class="sc-faq-page">
    <h2>سوالات متداول باشگاه</h2>
</div>
<div class="wrap faq" >
    
    <?php
    if($faqs){
    foreach($faqs as $i => $faq){ ?>
        <div class="section_box">

            <div id="question" class="question">
                
            <span> سوال <?php echo $i+1; ?> : <?php echo $faq->question; ?></span>  
            </div>
            <div class="answer">
            <span> <?php echo $faq->answer; ?> </span>  

            </div>
        </div>
        <?php
     }
    }else{ ?>
            <div class="section_box"><span>هنوز هیچ پرسشی برای باشگاه طراحی نشده است.</span> </div>
       <?php }
       ?>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.faq .section_box .question').forEach(question => {
        question.addEventListener('click', function () {
            const answer = this.nextElementSibling;
           
            const isExpanded = answer.classList.contains('show');
            
if (isExpanded) {
                // بسته شدن: بازگشت به حالت اول
                answer.classList.remove('show');
                question.classList.remove('active');
                answer.style.maxHeight = '0';
                answer.style.opacity = '0';
                console.log(question);
            } else {
                // باز شدن: نمایش پاسخ با انیمیشن
                answer.classList.add('show');
                question.classList.add('active');
                answer.style.maxHeight = answer.scrollHeight + 'px'; // ارتفاع واقعی پاسخ
                answer.style.opacity = '1';
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-faq-page h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>


