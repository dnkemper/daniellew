#!/bin/bash

# Determine the directory in which the script resides
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Function to perform database backup
backup_database() {
    NOW=$(date +"%m-%d-%Y-%T")
    BACKUP_FILE="migration-backup-$NOW.sql"
    echo "Backing up database to $BACKUP_FILE"
    ddev drush $DRUSH_ALIAS sql-dump > "$BACKUP_FILE"
}

# Function to reset the site
reset_site() {
    echo "Resetting site using reset-site.sh script"
    "$SCRIPT_DIR/reset-site.sh" $DRUSH_ALIAS
}

# Check if at least one argument is provided
if [ "$#" -lt 1 ]; then
    echo "Usage: run-migration.sh @drushalias [--backup] [--reset] [--shared-deps-only]"
    exit 1
fi

DRUSH_ALIAS=$1
shift # Shift arguments to the left

# Flags for backup, reset, and shared dependencies only
DO_BACKUP=false
DO_RESET=false
SHARED_DEPS_ONLY=false

# Ask if there is publication content
read -p "Is there publication content? (y/n) " PUB_CONTENT

# Process optional arguments
while (( "$#" )); do
    case "$1" in
        --backup)
            DO_BACKUP=true
            shift
            ;;
        --reset)
            DO_RESET=true
            shift
            ;;
        --shared-deps-only)
            SHARED_DEPS_ONLY=true
            shift
            ;;
        *)
            echo "Invalid option: $1"
            exit 1
            ;;
    esac
done

# Perform backup if --backup option is provided
if [ "$DO_BACKUP" = true ]; then
    backup_database
fi

# Perform reset if --reset option is provided
if [ "$DO_RESET" = true ]; then
    reset_site
fi

echo "Clearing cache for funsies"
ddev drush $DRUSH_ALIAS cr

echo "Migration process initiated for alias: $DRUSH_ALIAS"

# Adjust the migration command based on whether --shared-deps-only is provided
if [ "$SHARED_DEPS_ONLY" = true ]; then
    echo "Running migrations with tag 'shared-dep'"
    ddev drush $DRUSH_ALIAS migrate:import --tag=shared-dep --continue-on-failure --update
else
    echo "Running migrations with tag 'artsci'"
    ddev drush $DRUSH_ALIAS cr
    ddev drush $DRUSH_ALIAS uli
    # Import some d7 configs
    ddev drush $DRUSH_ALIAS sql:query --database=migrate --file=/var/www/html/scripts/migration/cleanup_d7.sql
    ddev drush $DRUSH_ALIAS mim olympian_d7_user_role --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_user --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_menu --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_pathauto_patterns --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim d7_file --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_image_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_cv_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim media_document_migration --execute-dependencies --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_theme_settings --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_system_site --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_webform --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_resource_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_article_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_event_categories --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_areas_of_interest --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_book_ --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_image_cards --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_taxonomy_term_leadership_administrative_staff --continue-on-failure --update
    ddev drush $DRUSH_ALIAS migrate:import olympian_d7_paragraphs_table_of_contents_anchors --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_tob	--continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow_slides --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slideshow --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_social_media_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses_publications  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_course_samples  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_lightbox_image --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_image_gallery --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sample_courses --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_staff --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_events --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_article --execute-dependencies --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_video_spotlights_and_articles --execute-dependencies --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_image --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_fullscreen_slideshow --continue-on-failure  --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_horizontal_slide --continue-on-failure  --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_multipurpose_slideshow --update --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_timeline_slides --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_slideshow --update --continue-on-failure  --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_sidebar_slideshow --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_resources_callout --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_two_column_content --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_webform --update --execute-dependencies --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_book --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_events --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_spotlights --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_full_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_with_caption --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_half_image_w_text --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_text_with_image --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_intro_with_links --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_full_screen_home_slide --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_testimonials --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resource --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_additional_resources --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_text_link_with_testimonial --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_call_to_action_blocks --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_link_title_and_body --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_featured_news --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_callout --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_instagram_twitter_section --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_article_spotlight --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_home_page --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_related_links  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_optional_callout  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_header  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_image_cards_landing  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faculty_landing_page --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_news_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_events_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_past_events_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_jump_to  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_resources_landing_page  --continue-on-failure --update --execute-dependencies
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_basic_page_content  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_content_2  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_tabs  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_contact_landing_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_faq --update  --continue-on-failure --execute-dependencies
    ddev drush $DRUSH_ALIAS mim d7_menu_links --execute-dependencies --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update
    ddev drush $DRUSH_ALIAS mim olympian_d7_url_alias --update  --continue-on-failure
ddev drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date field_date_all_day --clear
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_generic_page --execute-dependencies --update  --continue-on-failure
fi

# Run publication content commands if needed
if [ "$PUB_CONTENT" = "y" ]; then
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcement_feature --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_announcements --update

    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_award --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_awards_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_call_to_action --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degree_holder --update --continue-on-failure

    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_degrees_subsection --update --continue-on-failure

    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_featured_people --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_full_video --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery_image --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_gallery --update --continue-on-failure

    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_intro --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_person --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publications_featured_news --update --continue-on-failure

    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_story --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_student_stories --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_paragraphs_publication_testimonial_section --update --continue-on-failure
    ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_publication_page --update --migrate-debug
fi

ddev drush $DRUSH_ALIAS mim olympian_d7_node_complete_page  --continue-on-failure --update

ddev drush $DRUSH_ALIAS smart_date:migrate events field_event_smart_date field_event_date field_event_date field_date_all_day --clear
