<?php
defined('MOODLE_INTERNAL') || die();

class resource_kalturaswfdoc extends resource_base {

    function resource_kalturaswfdoc($cmid=0) {
        global $COURSE, $CFG;

        require_once($CFG->dirroot.'/blocks/kaltura/lib.php');
        require_once($CFG->dirroot.'/blocks/kaltura/locallib.php');
        require_js($CFG->wwwroot.'/blocks/kaltura/js/jquery.js');
        require_js($CFG->wwwroot.'/blocks/kaltura/js/kvideo.js');
        require_js($CFG->wwwroot.'/blocks/kaltura/js/swfobject.js');

        parent::resource_base($cmid);

        $this->release  = '1.2';

        // Add Kaltura block instance (needed for backup and restor purposes)
        $blockid = get_field('block', 'id', 'name', 'kaltura');

        if ($blockid) {

            if (!record_exists('block_instance', 'pageid', $COURSE->id, 'blockid', $blockid)) {

                $block              = new stdClass();
                $block->blockid     = $blockid;
                $block->pageid      = $COURSE->id;
                $block->pagetype    = 'course-view';
                $block->position    = 'r';
                $block->weight      = '3';
                $block->visible     = 0;
                $block->configdata  = 'Tjs=';

                insert_record('block_instance', $block);
            }
        }


    }

    function display() {

        global $CFG;

        $formatoptions = new object();
        $formatoptions->noclean = true;

        /// Are we displaying the course blocks?
        if ($this->resource->options == 'showblocks') {

            parent::display_course_blocks_start();

            $entry = get_record('block_kaltura_entries','context',"R_" . "$this->resource->id");

            if (trim(strip_tags($this->resource->alltext))) {

                echo $entry->title;
                $player_url = $CFG->wwwroot.'/blocks/kaltura/kswfdoc.php?context='.$this->course->id.'&entry_id='.$resource->alltext;
                $formatoptions = new object();
                $formatoptions->noclean = true;
                print_simple_box(format_text($resource->summary, FORMAT_MOODLE, $formatoptions, $this->course->id), "center");

                if ($resource->alltext) {
                    echo '<input style="margin-top:20px;" type="button" value="View video presentation" onclick="kalturaInitModalBox(\''. $player_url .'\', {width:780, height:400});">';
                }

            }

            parent::display_course_blocks_end();

        } else {

            /// Set up generic stuff first, including checking for access
            parent::display();

            /// Set up some shorthand variables
            $cm = $this->cm;
            $course = $this->course;
            $resource = $this->resource;

            $entry = get_record('block_kaltura_entries','context',"R_" . "$resource->id");

            $pagetitle = strip_tags($course->shortname.': '.format_string($resource->name));
            $inpopup = optional_param('inpopup', '', PARAM_BOOL);

            add_to_log($course->id, "resource", "view", "view.php?id={$cm->id}", $resource->id, $cm->id);
            $navigation = build_navigation($this->navlinks, $cm);

            print_header($pagetitle, $course->fullname, $navigation,
                    "", "", true, update_module_button($cm->id, $course->id, $this->strresource),
                    navmenu($course, $cm));

            if (trim(strip_tags($this->resource->alltext))) {
              echo $entry->title;
            }

            $formatoptions = new object();
            $formatoptions->noclean = true;
            print_simple_box(format_text($resource->summary, FORMAT_MOODLE, $formatoptions, $this->course->id), "center");

            if (trim(strip_tags($this->resource->alltext))) {

                $player_url = $CFG->wwwroot.'/blocks/kaltura/kswfdoc.php?context='.$this->course->id.'&entry_id='.$resource->alltext;

                if ($resource->alltext) {
                    echo '<input type="button" style="margin-top:20px;"  value="View video presentation" onclick="kalturaInitModalBox(\''. $player_url .'\', {width:783, height:400});">';
                }


            }

            $strlastmodified = get_string("lastmodified");
            echo "<div class=\"modified\">$strlastmodified: ".userdate($resource->timemodified)."</div>";

            print_footer($course);

        }
    }

    function add_instance($resource) {

        $dimensions = optional_param($_POST['dimensions'], '', PARAM_INT);
        $size       = optional_param($_POST['size'], '', PARAM_INT);
        $custwidth  = optional_param($_POST['custom_width'], '', PARAM_INT);
        $design     = optional_param($_POST['design'], '', PARAM_TEXT);
        $title      = optional_param($_POST['title'], 'No Title', PARAM_TEXT);

        $result = parent::add_instance($resource);

        if (false !== $result) {

            $entry                  = new kaltura_entry;
            $entry->entry_id        = $resource->alltext;
            $entry->courseid        = $resource->course;
            $entry->dimensions      = $dimensions;
            $entry->size            = $size;
            $entry->custom_width    = $custwidth;
            $entry->design          = $design;
            $entry->title           = $title;
            $entry->context         = "R_" . $result;
            $entry->entry_type      = KalturaEntryType::DOCUMENT;
            $entry->media_type      = KalturaMediaType::VIDEO;

            $entry->id = insert_record('block_kaltura_entries', $entry);
        }

        return $result;
    }

    function update_instance($resource) {

        $time = time();

        $result = parent::update_instance($resource);

        if (false !== $result) {

            $dimensions = optional_param($_POST['dimensions'], '', PARAM_INT);
            $size       = optional_param($_POST['size'], '', PARAM_INT);
            $custwidth  = optional_param($_POST['custom_width'], '', PARAM_INT);
            $design     = optional_param($_POST['design'], '', PARAM_TEXT);
            $title      = optional_param($_POST['title'], 'No Title', PARAM_TEXT);

            $entry                  = get_record('block_kaltura_entries','context',"R_" . "$resource->instance");
            $entry->entry_id        = $resource->alltext;
            $entry->dimensions      = $dimensions;
            $entry->size            = $size;
            $entry->custom_width    = $custwidth;
            $entry->design          = $design;
            $entry->title           = $title;

            update_record('block_kaltura_entries', $entry);
        }

        return $result;
    }

    function delete_instance($resource) {

        $entry = get_record('block_kaltura_entries','context',"R_" . "$resource->id");

        delete_records('block_kaltura_entries','context',"R_" . "$resource->id");

        return parent::delete_instance($resource);
    }

    function setup_elements(&$mform) {

        global $CFG, $USER;
        $isNew      = true;
        $ppt_id     = '';
        $video_id   = '';
        $dnld_url   = '';
        $partner_id = KalturaHelpers::getPlatformKey('kaltura_partner_id', 0);

        if (0 == $partner_id) {
            redirect($CFG->wwwroot . '/admin/settings.php?section=blocksettingkaltura');
            die();
        }

        $updateparam = optional_param('update', 0, PARAM_INT);

        if(!empty($updateparam)) {

            $isNew          = false;
            $item_id        = $updateparam;
            $result         = get_record('course_modules','id',$item_id);
            $result         = get_record('resource','id',$result->instance);
            $entry          = get_record('block_kaltura_entries','context', 'R_' . $result->id);
            $default_entry  = $entry;

            $url            = $CFG->wwwroot .'/blocks/kaltura/kswfdoc.php?entry_id='.$entry->entry_id . '&context=' . $this->course->id;
            $editSyncButton = '<button onclick="kalturaInitModalBox(\''.$url.'\', {width:783, height:400});return false;">' .
                              get_string('editsyncpoints','resource_kalturaswfdoc') . '</button>';

            $mform->addElement('static', 'edit_sync', get_string('editsyncpoints', 'resource_kalturaswfdoc'), $editSyncButton);

        } else {

            $last_entry_id = get_field('block_kaltura_entries','max(id)', 'id', 'id');
            if (!empty($last_entry_id)) {
                $default_entry = get_record('block_kaltura_entries','id',"$last_entry_id");
                $default_entry->title = "";
            } else {
                $default_entry = new kaltura_entry;
            }
        }

        $hidden_alltext = new HTML_QuickForm_hidden('alltext', $default_entry->dimensions, array('id' => 'id_alltext'));
        $mform->addElement($hidden_alltext);

        $hidden_popup = new HTML_QuickForm_hidden('popup', '', array('id' => 'id_popup'));
        $mform->addElement($hidden_popup);


        $hidden_dimensions = new HTML_QuickForm_hidden('dimensions', $default_entry->dimensions, array('id' => 'id_dimensions'));
        $mform->addElement($hidden_dimensions);

        $hidden_size = new HTML_QuickForm_hidden('size', $default_entry->size, array('id' => 'id_size'));
        $mform->addElement($hidden_size);

        $hidden_custom_width = new HTML_QuickForm_hidden('custom_width', $default_entry->custom_width, array('id' => 'id_custom_width'));
        $mform->addElement($hidden_custom_width);

        $hidden_design = new HTML_QuickForm_hidden('design', $default_entry->design, array('id' => 'id_design'));
        $mform->addElement($hidden_design);

        $hidden_title = new HTML_QuickForm_hidden('title', $default_entry->title, array('id' => 'id_title'));
        $mform->addElement($hidden_title);

        $hidden_entry_type = new HTML_QuickForm_hidden('entry_type', $default_entry->entry_type, array('id' => 'id_entry_type'));
        $mform->addElement($hidden_entry_type);

        $hidden_ppt = new HTML_QuickForm_hidden('ppt_input', $ppt_id, array('id' => 'id_ppt_input'));
        $mform->addElement($hidden_ppt);

        $hidden_video = new HTML_QuickForm_hidden('video_input', $video_id, array('id' => 'id_video_input'));
        $mform->addElement($hidden_video);

        $hidden_ppt_dnld = new HTML_QuickForm_hidden('ppt_dnld_url', $dnld_url, array('id' => 'id_ppt_dnld_url'));
        $mform->addElement($hidden_ppt_dnld);

        $hidden_ppt_dnld2 = new HTML_QuickForm_hidden('ppt_dnld_url2', $dnld_url, array('id' => 'id_ppt_dnld_url2'));
        $mform->addElement($hidden_ppt_dnld2);

        $hidden_enable_sync = new HTML_QuickForm_hidden('enable_sync', 0, array('id' => 'id_enable_sync'));
        $mform->addElement($hidden_enable_sync);

        // Has PPT, VIDEO and SFW hidden elements
        $hidden_has_ppt = new HTML_QuickForm_hidden('has_ppt', 0, array('id' => 'id_has_ppt'));
        $mform->addElement($hidden_has_ppt);

        $hidden_has_video = new HTML_QuickForm_hidden('has_video', 0, array('id' => 'id_has_video'));
        $mform->addElement($hidden_has_video);

        $hidden_has_swfdoc = new HTML_QuickForm_hidden('has_swfdoc', 0, array('id' => 'id_has_swfdoc'));
        $mform->addElement($hidden_has_swfdoc);

        // DEBUGGING ELEMENT
        $hidden_debug = new HTML_QuickForm_hidden('debug', 0, array('id' => 'id_debug'));
        $mform->addElement($hidden_debug);


        $resource       = $this->resource;
        $kaltura_client = KalturaHelpers::getKalturaClient();
        $thumbnail      = '';
        $vid_thumb      = '';
        $has_ppt        = '0';
        $has_video      = '0';

        if ($isNew) {

            $kaltura_cdn_url    = '';
            $ppt_thumb          = '';
            $sub_partner_id     = $partner_id * 100; // Thanks to http://www.kaltura.org/kaltura-terminology#kaltura-sub-partner-id

            $videolabel         = get_string('selectvideo', 'resource_kalturaswfdoc');
            $uploaddoclabel     = get_string('uploaddocument', 'resource_kalturaswfdoc');

            //Initialized kaltura javascript main variables
            require_js($CFG->wwwroot . '/blocks/kaltura/js/kaltura.main.js');

            // Initialized kaltura javascript main variables
            $kalturaprotal = new kaltura_jsportal();
            $jsoutput = $kalturaprotal->print_javascript(
                                 array(
                                  'wwwroot'             => $CFG->wwwroot,
                                  'userid'              => $USER->id,
                                  'courseid'            => $this->course->id,
                                  'kserviceurl'         => $kaltura_client->getConfig()->serviceUrl,
                                  'ksession'            => $kaltura_client->getKs(),
                                  'kpartnerid'          => $partner_id,
                                  'ksubpartnerid'       => $sub_partner_id,
                                  'txt_document'        => 'Document is currently being converted.<br/>'.
                                                           '<a href=\"javascript:check_ready(\'ppt\')\">Click here</a>'.
                                                           ' to check if conversion is done.',
                                ), false, true);

            require_js($CFG->wwwroot . '/blocks/kaltura/js/kaltura.lib.js');

            // Upload Video button properties
            $selvidbtn  = new HTML_QuickForm_input;
            $selvidbtn->setName('selectvideo');
            $selvidbtn->setType('button');
            $selvidbtn->setValue('Add Video');

            $cw_url = $CFG->wwwroot.'/blocks/kaltura/kcw.php?mod=ppt_resource';

            $display = empty($entry) ? 'display:inline;' : 'display:none;';
            $selvidbtn_attributes = array(
                'type' => 'button',
                'onclick' => 'kalturaInitModalBox(\''. $cw_url .'\', {width:763, height:433});'.
                             'add_swf_uploader();return false;',
                'id' => 'btn_selectvideo',
                'value' => $videolabel,
                'style' => $display,
            );

            // Video preview window properties
            //$thumbnail = 'testing.enable';
            $display = empty($thumbnail) ? 'none;' : 'inline;';
            $div_wait_thumbnail = '<div style="border:1px solid #bcbab4; background-color:#f5f1e9; '.
                                'width:140px; height:105px; float:left; text-align:center;'.
                                'font-size:85%; display:inline" id="divWait">'
                                 . $thumbnail . '</div>';

            $waitimagejs = get_wait_image('divWait', 'id_video_input', false, true);

            $mform->addElement('static', 'videoholder', '', $waitimagejs);

            $videopreviewdiv  = new HTML_QuickForm_static;
            $videopreviewdiv->setText($div_wait_thumbnail);

            $videouploaddiv_attributes = array(
                'id' => 'videothumbnail',
                'style' => (empty($entry) ? 'display:inline' : 'display:none'),
            );



            /* ---------------- */

            $upldocbtn = new HTML_QuickForm_input;
            $upldocbtn->setName('btn_uploaddoc');
            $upldocbtn->setType('button');
            $upldocbtn->setValue('Upload Document');

            $doclabel = get_string('uploaddocument', 'resource_kalturaswfdoc');

            $upldocbtn_attributes = array(
                'type' => 'button',
                'id' => 'btn_uploaddoc',
                'value' => $doclabel,
            );

            // Dummy Div properties
            $divdummydiv = '<span style="border: 0px solid black; position: relative;'.
                           ' left: -130px; width: 125px;" id="divKalturaKupload"></span>';

            $dummyelement  = new HTML_QuickForm_static;
            $dummyelement->setText($divdummydiv);

            $dummydiv_attributes = array(
                'id' => 'dummydivcontainer',
                'style' => (empty($entry) ? 'display:inline' : 'display:none'),
            );


            // document preview window properties
            //$ppt_thumb = 'testing.enable';
            $display = empty($ppt_thumb) ? 'none;' : 'inline;';
            $divdocumentpreview = '<div style="border:1px solid #bcbab4; background-color:#f5f1e9; '.
                                'width:140px; height:105px; float:left; text-align:center;'.
                                'font-size:85%; display:inline;" id="thumb_doc_holder">'
                                . $ppt_thumb . '</div>';

            $documentpreviewdiv  = new HTML_QuickForm_static;
            $documentpreviewdiv->setText($divdocumentpreview);

            $videouploaddiv_attributes = array(
                'id' => 'documentpreview',
                'style' => (empty($entry) ? 'display:inline' : 'display:none'),
            );


            $videopreviewdiv->setAttributes($videouploaddiv_attributes);
            $selvidbtn->setAttributes($selvidbtn_attributes);
            $upldocbtn->setAttributes($upldocbtn_attributes);
            $dummyelement->setAttributes($dummydiv_attributes);


            // Print the upload video and video preview elements
            $videogroup = array();
            $videogroup[] = &$selvidbtn;
            $videogroup[] = &$videopreviewdiv;
            //$videogroup[] = &$upldocbtn;

            $videogroup_contents        = empty($entry) ? get_string('selectvideo', 'resource_kalturaswfdoc') : '';
            $mform->addElement('group', 'videogroup', $videogroup_contents, $videogroup);


            // Print the upload document and preview elements
            $documentgroup = array();
            $documentgroup[] = &$upldocbtn;
            $documentgroup[] = &$documentpreviewdiv;
            $documentgroup[] = &$dummyelement;

            $documentgroup_contents     = empty($entry) ? get_string('uploaddocument', 'resource_kalturaswfdoc') : '';
            $mform->addElement('group', 'documentgroup', $documentgroup_contents, $documentgroup);

            // print Sync button
            $attributes = array('id' => 'id_sync_btn',
                                'type' => 'button',
                                'value' => get_string('syncpoints', 'resource_kalturaswfdoc'),
                                'onclick' => 'save_sync()');
            $sync_btn = new HTML_QuickForm_input('sync_btn_name', '', $attributes);
            $mform->addElement($sync_btn);

            $mform->disabledIf('sync_btn_name', 'enable_sync', 'eq', 0);

            $mform->addElement('static', 'jsblock', '', $jsoutput);

        }

        $mform->addElement('header', 'displaysettings', get_string('display', 'resource'));

    }
}
?>
