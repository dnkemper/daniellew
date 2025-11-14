#!/bin/bash
Red='\033[0;31m'          # Red
Green='\033[0;32m'
blue="\033[0;34m"
NC='\033[0m' # No Color
# Define the base directory for backup files
BACKUP_DIR="/home/drupaladm/d9main/d7data/backups"
# Load environment variables from the .env file
if [ -f /home/drupaladm/d9main/.env ]; then
  export $(grep -v '^#' /home/drupaladm/d9main/.env | xargs)
fi

# Ask if there is publication content
read -p "Is there publication content? (y/n) [n]" PUB_CONTENT
read -p "Do you want to reset your local environment? (y/n) [y]: " RESET_LOCAL
RESET_LOCAL="${RESET_LOCAL:-y}"
if [ "$RESET_LOCAL" = "y" ]; then

    # Set your desired default alias
default_alias="biology"

# Set your desired default alias
default_alias="biology"

# Display instructions
echo "You need the trailing / and be sure to choose the correct option for the URL."
echo "For example, if you migrate it.artsci.wustl.edu, ${blue}it${NC} would be alias, and the URL would be ${blue}https://it.artsci.wustl.edu/${NC}"

# Prompt for the alias
read -p "Enter the name of the D7 site you want to migrate (example: it, history, anthropology) [${default_alias}]: " alias
alias="${alias:-$default_alias}"

# Offer two URL options
option1="https://${alias}.wustl.edu/"
option2="https://${alias}.artsci.wustl.edu/"

# Display options
echo "Please select the appropriate D7 site URL:"
echo "1) ${option1} (default)"
echo "2) ${option2}"

# Prompt for selection with the default set to option 1
read -p "Enter the number (1 or 2) corresponding to the URL [1]: " selection
selection="${selection:-1}"  # Default to 1 if no input is given

# Validate selection
if [ "$selection" == "2" ]; then
    d7_site="$option2"
else
    d7_site="$option1"
fi

echo "The final D7 site URL is: ${d7_site}"


# Define the .env file path
env_file="/home/drupaladm/d9main/.env"

# Check if the .env file exists
if [ ! -f "$env_file" ]; then
    echo "The .env file does not exist in the current directory."
    exit 1
fi

# Edit the .env file to set the D7_SITE variable using a different delimiter (|)
if grep -q "D7_SITE=" "$env_file"; then
    # If D7_SITE already exists, replace it
    sed -i '' "s|^D7_SITE=.*|D7_SITE=$d7_site|" "$env_file"
else
    # If it doesn't exist, add it
    echo "D7_SITE=$d7_site" >> "$env_file"
fi

# Run the ddev import-db command for d10base installation
/home/drupaladm/d9main/vendor/bin/drush sql-sync @stage.migrate ${alias}.stage --yes
/home/drupaladm/d9main/vendor/bin/drush ${alias}.stage cim --yes
/home/drupaladm/d9main/vendor/bin/drush ${alias}.stage sql-query --database=migrate --file=/home/drupaladm/d9main/d7data/backups/drupal_${alias}_backup.sql

echo "Updated D7_SITE in $env_file to: $d7_site"

# Run the ddev import-db command for the D7 database

# Notify user
echo "Database import command executed for the alias: $alias"
fi

DRUSH_ALIAS=$1

echo "Clearing cache for funsies"
/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS cr
/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS sapi-disa
echo "Migration process initiated for alias: $DRUSH_ALIAS"

# Adjust the migration command based on whether --shared-deps-only is provided
if [ "$SHARED_DEPS_ONLY" = true ]; then
    echo "Running migrations with tag 'shared-dep'"
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS migrate:import --tag=shared-dep --continue-on-failure --update
else
    echo "Running migrations with tag 'artsci'"
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS cr
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS uli
    # Import some d7 configs
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS sql:query --database=migrate --file=/home/drupaladm/d9main/scripts/migration/cleanup_d7.sql
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_user_role --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_system_site  --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_user --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS uli
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_media_folders --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_menu --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_pathauto_patterns --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim d7_file --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim media_image_migration --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim media_cv_migration --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim media_document_migration --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS migrate:import olympian_d7_theme_settings --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS migrate:import olympian_d7_theme_settings --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS migrate:import olympian_d7_system_site --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_resource_categories --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_article_categories --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_event_categories --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_areas_of_interest --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_book_ --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_image_cards --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_leadership_administrative_staff --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS migrate:import olympian_d7_paragraphs_table_of_contents_anchors --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob	--continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow_slides --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --execute-dependencies --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_social_media_links --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses_publications  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_samples  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_lightbox_image --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_image_gallery --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_staff --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_testimonial --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_events --execute-dependencies --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_article --execute-dependencies --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_video_spotlights_and_articles --execute-dependencies --continue-on-failure  --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_image --continue-on-failure  --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_slideshow --continue-on-failure  --update --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_horizontal_slide --continue-on-failure  --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_multipurpose_slideshow --update --continue-on-failure --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --update --continue-on-failure  --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_slideshow --update --continue-on-failure  --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sidebar_slideshow --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_resources_callout --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_two_column_content --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_book --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonial --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_events --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlight --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlights --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_full_w_text --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_with_caption --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_half_image_w_text --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_text_with_image --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_links --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_full_screen_home_slide --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonials --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resource --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resources --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_text_link_with_testimonial --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_call_to_action_blocks --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_link_title_and_body --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_news --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_callout --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_instagram_twitter_section --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_article_spotlight --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_home_page --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_related_links  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_optional_callout  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_header  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards_landing  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_landing_page --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_news_landing_page  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_events_landing_page  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_past_events_landing_page  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_jump_to  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_basic_page_content  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_content_2  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_contact_landing_page  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_faq --update  --continue-on-failure --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim d7_menu_links --execute-dependencies --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_menu --execute-dependencies --continue-on-failure --update
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_url_alias --update  --continue-on-failure
    #need to do webform stuff AFTER url_alias so the node aliases don't mess up the webforms
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_webform --execute-dependencies --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_webform_submission --execute-dependencies --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_webform --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date field_date_all_day --clear
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim d7_aggregator_item --update --execute-dependencies --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim d7_aggregator_feed --update --execute-dependencies --continue-on-failure

fi

    # Run publication content commands if needed
if [ "$PUB_CONTENT" = "y" ]; then

    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_intro --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement_feature --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcements --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_featured_people --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_featured_news --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree_holder --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_subsection --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_section --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery_image --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_full_video --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_call_to_action --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_table_of_contents_anchors --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob --update --continue-on-failure
    /home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_publication_page  --execute-dependencies --continue-on-failure --update

fi

/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update
/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date field_date_all_day --clear
/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS cim -y
/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS cr
/home/drupaladm/d9main/vendor/bin/drush $DRUSH_ALIAS uli
