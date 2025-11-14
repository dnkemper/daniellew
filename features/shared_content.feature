@api @parallel
Feature: Shared Content
  Check some UI and permissions requirements for shared content
  
  Scenario: Shared content link
    Given I am logged in as a user with the "site_staff" role
    When I am on the homepage
    Then I should see the text "Content Sharing"
    When I click "Content Sharing"
    Then I should see the text "Shared Content"
    And I should see the text "Refresh Feed"