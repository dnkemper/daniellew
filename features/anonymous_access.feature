@api @parallel
Feature: Anonymous Access
  Ensure parts of site can or can't be accessed anonymously
  
  Scenario: Admin content view
    Given I am an anonymous user
    When I am on "admin/content"
    Then I should get a 403 HTTP response
  
  Scenario: Admin content view
    Given I am an anonymous user
    When I am on "admin/people"
    Then I should get a 403 HTTP response