jQuery.noConflict();
jQuery(document).ready(function()
{

    // --------------------------------------------------------------------

    // Used for setting elements heights the same as the biggest of the bunch
    jQuery('.parallel').each(function()
    {
        var tallest_elem = 0;
        jQuery(this).find('.parallel_target').each(function(i)
        {
            tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
        });

        jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
    });

    // --------------------------------------------------------------------

    // Used for styling labels so they all line up correctly
    jQuery("form").each(function()
    {
        var w = 0;
        jQuery("label", this).each(function()
        {
            if (jQuery(this).width() > w)
            {
                w = jQuery(this).width();
            }
        });

        if (w > 0)
        {
            var percent_width = (w + 5) + "px";
            jQuery("label", this).each(function()
            {
                jQuery(this).css('width', percent_width);
                jQuery(this).css('display', 'inline-block');
            });
        }
    });

    // --------------------------------------------------------------------

});