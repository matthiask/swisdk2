<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	interface IComponent {
		public function run();
	}

	interface IHtmlComponent extends IComponent {
		public function html();
	}

	interface IFeedComponent extends IComponent {
		public function send_feed();
	}

?>
