# wp-meetup-events

Synchronize events created by "The Events Calendar" wordpress plugin
to meetup on event save / update. This makes it really easy to have an
authoritative version in wordpress, but still have folk able to RSVP
via meetup.

## Background

Meetup makes for a good platform for getting the word out, but having
all the authoritative information for your group be only on meetup is
something that I'd rather avoid. When doing the mhvlug drupal website
we did this by using a custom event type and custom code to sync to
meetup.

For wordpress The Events Calendar seems like the clear winner for an
events system, so just building on that saves us a bunch of time.

## Configuration

The only configuration needed is a meetup API key, and group id that
you will sync against. You can set those values in ``Settings ->
Writing``

## TODO

* RSVP button on events

  There should be an RSVP button displayed on events in the future
  that sends you to meetup to that event to RSVP. This will involve
  hooking one of the render actions for The Events Calendar.

* Venue setting

  Right now venues aren't sent to meetup. There was a fuzzy match
  algorithm in meetup_events drupal module that could be implemented
  here.
