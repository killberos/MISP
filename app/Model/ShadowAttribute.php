<?php

App::uses('AppModel', 'Model');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

/**
 * Attribute Model
 *
 * @property Event $Event
 */
class ShadowAttribute extends AppModel {

	public $combinedKeys = array('event_id', 'category', 'type');

	public $name = 'ShadowAttribute';				// TODO general

	public $actsAs = array(
		'SysLogLogable.SysLogLogable' => array(	// TODO Audit, logable
			'userModel' => 'User',
			'userKey' => 'user_id',
			'change' => 'full'),
		'Trim',
		'Containable',
		'Regexp' => array('fields' => array('value', 'value2')),
	);

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Event' => array(
			'className' => 'Event',
			'foreignKey' => 'event_id',
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'counterCache' => true
		),
		'Org' => array(
				'className' => 'Organisation',
				'foreignKey' => 'org_id'
		),
		'EventOrg' => array(
				'className' => 'Organisation',
				'foreignKey' => 'event_org_id'
		),
	);

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'value';

/**
 * Virtual field
 *
 * @var array
 */
	public $virtualFields = array(
			'value' => 'IF (ShadowAttribute.value2="", ShadowAttribute.value1, CONCAT(ShadowAttribute.value1, "|", ShadowAttribute.value2))',
	); // TODO hardcoded

/**
 * Field Descriptions
 * explanations of certain fields to be used in various views
 *
 * @var array
 */
	public $fieldDescriptions = array(
			'signature' => array('desc' => 'Is this attribute eligible to automatically create an IDS signature (network IDS or host IDS) out of it ?'),
			//'private' => array('desc' => 'Prevents upload of this single Attribute to other CyDefSIG servers', 'formdesc' => 'Prevents upload of <em>this single Attribute</em> to other CyDefSIG servers.<br/>Used only when the Event is NOT set as Private')
	);

	// if these then a category my have upload to be zipped

	public $zippedDefinitions = array(
			'malware-sample'
	);

	// if these then a category my have upload

	public $uploadDefinitions = array(
			'attachment'
	);

	// definitions of categories
	public $categoryDefinitions;

	public $order = array("ShadowAttribute.event_id" => "DESC", "ShadowAttribute.type" => "ASC");

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'event_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'type' => array(
			// currently when adding a new attribute type we need to change it in both places
			'rule' => array('validateTypeValue'),
			'message' => 'Options depend on the selected category.',
			//'allowEmpty' => false,
			'required' => true,
			//'last' => false, // Stop validation after this rule
			//'on' => 'create', // Limit validation to 'create' or 'update' operations

		),
		// this could be initialized from categoryDefinitions but dunno how at the moment
		'category' => array(
			'validCategory' => array(
				'rule' => array('validCategory'),
				'message' => 'Options : Payload delivery, Antivirus detection, Payload installation, Files dropped ...'
			),
		),
		'value' => array(
			'valueNotEmpty' => array(
				'rule' => array('valueNotEmpty'),
			),
			'userdefined' => array(
				'rule' => array('validateAttributeValue'),
				'message' => 'Value not in the right type/format. Please double check the value or select type "other".',
			),
		),
		'to_ids' => array(
			'boolean' => array(
				'rule' => array('boolean'),
				'required' => false,
			),
		),
		'uuid' => array(
			'uuid' => array(
				'rule' => array('uuid'),
			),
		),
		'proposal_to_delete' => array(
				'boolean' => array(
						'rule' => array('boolean'),
				),
		),
	);

	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);
		$this->virtualFields = Set::merge($this->virtualFields,array(
			//'distribution' => 'IF (Attribute.private=true, "Your organization only", IF (Attribute.cluster=true, "This Community-only", "All communities"))',
			//'distribution' => 'IF (ShadowAttribute.private=true AND ShadowAttribute.cluster=false, "Your organization only", IF (ShadowAttribute.private=true AND ShadowAttribute.cluster=true, "This server-only", IF (ShadowAttribute.private=false AND ShadowAttribute.cluster=true, "This Community-only", IF (ShadowAttribute.communitie=true, "Connected communities" , "All communities"))))',
		));
		$this->fieldDescriptions = Set::merge($this->fieldDescriptions,array(
			//'distribution' => array('desc' => 'This fields indicates the intended distribution of the attribute (same as when adding an event, see Add Event)'),
		));
		$this->categoryDefinitions = $this->Event->Attribute->categoryDefinitions;
		$this->typeDefinitions = $this->Event->Attribute->typeDefinitions;
	}

	// The Associations below have been created with all possible keys, those that are not needed can be removed
	
/**
 * beforeSave
 *
 * @throws InternalErrorException
 * @return bool always true
 */
	public function beforeSave($options = array()) {
		// explode value of composite type in value1 and value2
		// or copy value to value1 if not composite type
		if (!empty($this->data['ShadowAttribute']['type'])) {
			$compositeTypes = $this->getCompositeTypes();
			// explode composite types in value1 and value2
			$pieces = explode('|', $this->data['ShadowAttribute']['value']);
			if (in_array($this->data['ShadowAttribute']['type'], $compositeTypes)) {
				if (2 != count($pieces)) {
					throw new InternalErrorException('Composite type, but value not explodable');
				}
				$this->data['ShadowAttribute']['value1'] = $pieces[0];
				$this->data['ShadowAttribute']['value2'] = $pieces[1];
			} else {
				$total = implode('|', $pieces);
				$this->data['ShadowAttribute']['value1'] = $total;
				$this->data['ShadowAttribute']['value2'] = '';
			}
		}
		if (!isset($this->data['ShadowAttribute']['deleted'])) $this->data['ShadowAttribute']['deleted'] = false;
		if ($this->data['ShadowAttribute']['deleted']) $this->__beforeDeleteCorrelation($this->data['ShadowAttribute']);
		return true;
	}

	private function __beforeDeleteCorrelation(&$sa) {
		$temp = $sa;
		if (isset($temp['ShadowAttribute'])) $temp = $temp['ShadowAttribute'];
		$this->ShadowAttributeCorrelation = ClassRegistry::init('ShadowAttributeCorrelation');
		$this->ShadowAttributeCorrelation->deleteAll(array('ShadowAttributeCorrelation.1_shadow_attribute_id' => $temp['id']));
	}
	
	private function __afterSaveCorrelation(&$sa) {
		$temp = $sa;
		if (isset($temp['ShadowAttribute'])) $temp = $temp['ShadowAttribute'];
		if (in_array($temp['type'], $this->Event->Attribute->nonCorrelatingTypes)) return;
		$this->ShadowAttributeCorrelation = ClassRegistry::init('ShadowAttributeCorrelation');
		$shadow_attribute_correlations = array();
		$fields = array('value1', 'value2');
		$correlatingValues = array($temp['value1']);
		if (!empty($temp['value2'])) $correlatingValues[] = $temp['value2'];
		foreach ($correlatingValues as $k => $cV) {
			$correlatingAttributes[$k] = $this->Event->Attribute->find('all', array(
					'conditions' => array(
							'AND' => array(
									'OR' => array(
											'Attribute.value1' => $cV,
											'Attribute.value2' => $cV
									),
									'Attribute.type !=' => $this->Event->Attribute->nonCorrelatingTypes,
									'Attribute.deleted' => false
							),
					),
					'recursive => -1',
					'fields' => array('Attribute.event_id', 'Attribute.id', 'Attribute.distribution', 'Attribute.sharing_group_id'),
					'contain' => array('Event' => array('fields' => array('Event.id', 'Event.date', 'Event.info', 'Event.org_id', 'Event.distribution', 'Event.sharing_group_id'))),
					'order' => array(),
			));
			foreach ($correlatingAttributes[$k] as $key => &$correlatingAttribute) {
				if ($correlatingAttribute['Attribute']['event_id'] == $temp['event_id']) unset($correlatingAttributes[$k][$key]);
			}
			foreach ($correlatingAttributes as $k => $cA) {
				foreach ($cA as $corr) {
					$shadow_attribute_correlations[] = array(
							'value' => $correlatingValues[$k],
							'1_event_id' => $temp['event_id'],
							'1_shadow_attribute_id' => $temp['id'],
							'event_id' => $corr['Attribute']['event_id'],
							'attribute_id' => $corr['Attribute']['id'],
							'org_id' => $corr['Event']['org_id'],
							'distribution' => $corr['Event']['distribution'],
							'a_distribution' => $corr['Attribute']['distribution'],
							'sharing_group_id' => $corr['Event']['sharing_group_id'],
							'a_sharing_group_id' => $corr['Attribute']['sharing_group_id'],
							'date' => $corr['Event']['date'],
							'info' => $corr['Event']['info'],
					);
				}
			}
		}
		if (!empty($shadow_attribute_correlations)) $this->ShadowAttributeCorrelation->saveMany($shadow_attribute_correlations);
	}
	
	public function afterSave($created, $options = array()) {
		$result = true;
		// if the 'data' field is set on the $this->data then save the data to the correct file
		if (isset($this->data['ShadowAttribute']['deleted']) && $this->data['ShadowAttribute']['deleted']) {
			$sa = $this->find('first', array('conditions' => array('ShadowAttribute.id' => $this->data['ShadowAttribute']['id']), 'recursive' => -1, 'fields' => array('ShadowAttribute.id', 'ShadowAttribute.event_id', 'ShadowAttribute.type')));
			if ($this->typeIsAttachment($sa['ShadowAttribute']['type'])) {
				// FIXME secure this filesystem access/delete by not allowing to change directories or go outside of the directory container.
				// only delete the file if it exists
				$filepath = APP . "files" . DS . 'shadow' . DS . $sa['ShadowAttribute']['event_id'] . DS . $sa['ShadowAttribute']['id'];
				$file = new File($filepath);
				if ($file->exists()) {
					if (!$file->delete()) {
						throw new InternalErrorException('Delete of file attachment failed. Please report to administrator.');
					}
				}
			}
		} else {
			if (isset($this->data['ShadowAttribute']['type']) && $this->typeIsAttachment($this->data['ShadowAttribute']['type']) && !empty($this->data['ShadowAttribute']['data'])) {
				$result = $result && $this->saveBase64EncodedAttachment($this->data['ShadowAttribute']);
			}
		}
		if ((isset($this->data['ShadowAttribute']['deleted']) && $this->data['ShadowAttribute']['deleted']) || (isset($this->data['ShadowAttribute']['proposal_to_delete']) && $this->data['ShadowAttribute']['proposal_to_delete'])) {
			// this is a deletion
			// Could be a proposal to delete or flagging a proposal that it was discarded / accepted - either way, we don't want to correlate here for now
		} else {
			$this->__afterSaveCorrelation($this->data['ShadowAttribute']);
		}
		return $result;
	}

	public function beforeDelete($cascade = true) {
		// delete attachments from the disk
		$this->read(); // first read the attribute from the db
		if ($this->typeIsAttachment($this->data['ShadowAttribute']['type'])) {
			// FIXME secure this filesystem access/delete by not allowing to change directories or go outside of the directory container.
			// only delete the file if it exists
			$filepath = APP . "files" . DS . 'shadow' . DS . $this->data['ShadowAttribute']['event_id'] . DS . $this->data['ShadowAttribute']['id'];
			$file = new File($filepath);
			if ($file->exists()) {
				if (!$file->delete()) {
					throw new InternalErrorException('Delete of file attachment failed. Please report to administrator.');
				}
			}
		}
	}

	public function beforeValidate($options = array()) {
		parent::beforeValidate();
		// remove leading and trailing blanks
		//$this->trimStringFields(); // TODO
		if (isset($this->data['ShadowAttribute']['value'])) $this->data['ShadowAttribute']['value'] = trim($this->data['ShadowAttribute']['value']);

		if (!isset($this->data['ShadowAttribute']['type'])) {
			return false;
		}
		
		if (empty($this->data['ShadowAttribute']['timestamp'])) {
			$date = new DateTime();
			$this->data['ShadowAttribute']['timestamp'] = $date->getTimestamp();
		}

		// make some last changes to the inserted value
		$this->data['ShadowAttribute']['value'] = $this->Event->Attribute->modifyBeforeValidation($this->data['ShadowAttribute']['type'], $this->data['ShadowAttribute']['value']);

		// generate UUID if it doesn't exist
		if (empty($this->data['ShadowAttribute']['uuid'])) {
			$this->data['ShadowAttribute']['uuid'] = $this->generateUuid();
		}

		// always return true, otherwise the object cannot be saved
		return true;
	}

	public function validateTypeValue($fields) {
		$category = $this->data['ShadowAttribute']['category'];
		if (isset($this->categoryDefinitions[$category]['types'])) {
			return in_array($fields['type'], $this->categoryDefinitions[$category]['types']);
		}
		return false;
	}
	
	public function validCategory($fields) {
		return $this->Event->Attribute->validCategory($fields);
	}

	public function validateAttributeValue($fields) {
		$value = $fields['value'];
		return $this->Event->Attribute->runValidation($value, $this->data['ShadowAttribute']['type']);
	}

	public function getCompositeTypes() {
		// build the list of composite Attribute.type dynamically by checking if type contains a |
		// default composite types
		$compositeTypes = array('malware-sample');	// TODO hardcoded composite
		// dynamically generated list
		foreach (array_keys($this->typeDefinitions) as $type) {
			$pieces = explode('|', $type);
			if (2 == count($pieces)) {
				$compositeTypes[] = $type;
			}
		}
		return $compositeTypes;
	}

	public function typeIsMalware($type) {
		if (in_array($type, $this->zippedDefinitions)) {
			return true;
		} else {
			return false;
		}
	}

	public function typeIsAttachment($type) {
		if ((in_array($type, $this->zippedDefinitions)) || (in_array($type, $this->uploadDefinitions))) {
			return true;
		} else {
			return false;
		}
	}

	public function base64EncodeAttachment($attribute) {
		$filepath = APP . "files" . DS . 'shadow' . DS . $attribute['event_id'] . DS. $attribute['id'];
		$file = new File($filepath);
		if (!$file->exists()) {
			return '';
		}
		$content = $file->read();
		return base64_encode($content);
	}

	public function saveBase64EncodedAttachment($attribute) {
		$rootDir = APP . DS . "files" . DS . 'shadow' . DS . $attribute['event_id'];
		$dir = new Folder($rootDir, true);						// create directory structure
		$destpath = $rootDir . DS . $attribute['id'];
		$file = new File($destpath, true);						// create the file
		$decodedData = base64_decode($attribute['data']);		// decode
		if ($file->write($decodedData)) {						// save the data
			return true;
		} else {
			// error
			return false;
		}
	}

/**
 * add_attachment method
 *
 * @return void
 */
	public function uploadAttachment($fileP, $realFileName, $malware, $eventId = null, $category = null, $extraPath = '', $fullFileName = '') {
		// Check if there were problems with the file upload
		// only keep the last part of the filename, this should prevent directory attacks
		$filename = basename($fileP);
		$tmpfile = new File($fileP);

		// save the file-info in the database
		$this->create();
		$this->data['ShadowAttribute']['event_id'] = $eventId;
		if ($malware) {
			$md5 = !$tmpfile->size() ? md5_file($fileP) : $tmpfile->md5();
			$this->data['ShadowAttribute']['category'] = $category ? $category : "Payload delivery";
			$this->data['ShadowAttribute']['type'] = "malware-sample";
			$this->data['ShadowAttribute']['value'] = $fullFileName ? $fullFileName . '|' . $md5 : $filename . '|' . $md5; // TODO gives problems with bigger files
			$this->data['ShadowAttribute']['to_ids'] = 1; // LATER let user choose to send this to IDS
		} else {
			$this->data['ShadowAttribute']['category'] = $category ? $category : "Artifacts dropped";
			$this->data['ShadowAttribute']['type'] = "attachment";
			$this->data['ShadowAttribute']['value'] = $fullFileName ? $fullFileName : $realFileName;
			$this->data['ShadowAttribute']['to_ids'] = 0;
		}

		if ($this->save($this->data)) {
			// attribute saved correctly in the db
		} else {
			// do some?
		}

		// no errors in file upload, entry already in db, now move the file where needed and zip it if required.
		// no sanitization is required on the filename, path or type as we save
		// create directory structure
		if (PHP_OS == 'WINNT') {
			$rootDir = APP . "files" . DS . $eventId;
		} else {
			$rootDir = APP . "files" . DS . $eventId;
		}
		$dir = new Folder($rootDir, true);
		// move the file to the correct location
		$destpath = $rootDir . DS . $this->getId(); // id of the new attribute in the database
		$file = new File($destpath);
		$zipfile = new File($destpath . '.zip');
		$fileInZip = new File($rootDir . DS . $extraPath . $filename); // FIXME do sanitization of the filename

		// zip and password protect the malware files
		if ($malware) {
			// TODO check if CakePHP has no easy/safe wrapper to execute commands
			$execRetval = '';
			$execOutput = array();
			exec("zip -j -P infected " . $zipfile->path . ' \'' . addslashes($fileInZip->path) . '\'', $execOutput, $execRetval);
			if ($execRetval != 0) { // not EXIT_SUCCESS
				// TODO: error-handling
			}
			$fileInZip->delete(); // delete the original non-zipped-file
			rename($zipfile->path, $file->path); // rename the .zip to .nothing
		} else {
			$fileAttach = new File($fileP);
			rename($fileAttach->path, $file->path);
		}
	}

	public function checkComposites() {
		$compositeTypes = $this->getCompositeTypes();
		$fails = array();
		$attributes = $this->find('all', array('recursive' => 0));

		foreach ($attributes as $attribute) {
			if ((in_array($attribute['ShadowAttribute']['type'], $compositeTypes)) && (!strlen($attribute['ShadowAttribute']['value1']) || !strlen($attribute['ShadowAttribute']['value2']))) {
				$fails[] = $attribute['ShadowAttribute']['event_id'] . ':' . $attribute['ShadowAttribute']['id'];
			}
		}
		return $fails;
	}
	
	public function setDeleted($id) {
		$this->Behaviors->detach('SysLogLogable.SysLogLogable');
		$sa = $this->find('first', array('conditions' => array('ShadowAttribute.id' => $id), 'recusive' => -1));
		if (empty($sa)) return false;
		$date = new DateTime();
		$sa['ShadowAttribute']['deleted'] = 1;
		$sa['ShadowAttribute']['timestamp'] = $date->getTimestamp();
		$this->save($sa);
		return true;
	}
	
	public function findOldProposal($sa) {
		$oldsa = $this->find('first', array(
			'conditions' => array(
				'event_uuid' => $sa['event_uuid'],
				'value' => $sa['value'],
				'type' => $sa['type'],
				'category' => $sa['category'],
				'to_ids' => $sa['to_ids'],
				'comment' => $sa['comment']
			),
		));
		if (empty($oldsa)) return false;
		else return $oldsa['ShadowAttribute'];
	}
	
	public function getEventContributors($id) {
		$orgs = $this->find('all', array('fields' => array('DISTINCT(org_id)'), 'conditions' => array('event_id' => $id), 'order' => false));
		$org_ids = array();
		foreach ($orgs as $org) {
			$org_ids[] = $org['ShadowAttribute']['org_id'];
		}
		return $org_ids;
	}
	

	public function sendProposalAlertEmail($id) {
		$this->Event->recursive = -1;
		$event = $this->Event->read(null, $id);
	
		// If the event has an e-mail lock, return
		if ($event['Event']['proposal_email_lock'] == 1) {
			return;
		} else {
			$this->setProposalLock($id);
		}
		$this->User = ClassRegistry::init('User');
		$this->User->recursive = -1;
		$orgMembers = $this->User->find('all',array(
				'conditions' => array(
						'org_id' => $event['Event']['orgc_id'],
						'contactalert' => 1,
						'disabled' => 0
				),
				'fields' => array('email', 'gpgkey', 'certif_public', 'contactalert', 'id')
		));
	
		$body = "Hello, \n\n";
		$body .= "A user of another organisation has proposed a change to an event created by you or your organisation. \n\n";
		$body .= 'To view the event in question, follow this link: ' . Configure::read('MISP.baseurl') . '/events/view/' . $id . "\n";
		$subject =  "[" . Configure::read('MISP.org') . " MISP] Proposal to event #" . $id;
		$result = true;
		foreach ($orgMembers as &$user) {
			$result = $this->User->sendEmail($user, $body, $body, $subject) && $result;
		}
		return $result;
	}
	

	public function setProposalLock($id, $lock = true) {
		$this->Event->recursive = -1;
		$event = $this->Event->read(null, $id);
		if ($lock) {
			$event['Event']['proposal_email_lock'] = 1;
		} else {
			$event['Event']['proposal_email_lock'] = 0;
		}
		$fieldList = array('proposal_email_lock', 'id', 'info');
		$this->Event->save($event, array('fieldList' => $fieldList));
	}
	
	public function generateCorrelation($jobId = false) {
		$this->ShadowAttributeCorrelation = ClassRegistry::init('ShadowAttributeCorrelation');
		$this->ShadowAttributeCorrelation->deleteAll(array('id !=' => 0), false);
		// get all proposals..
		$proposals = $this->find('all', array('recursive' => -1, 'conditions' => array('ShadowAttribute.deleted' => 0, 'ShadowAttribute.proposal_to_delete' => 0)));
		$proposalCount = count($proposals);
		if ($jobId && Configure::read('MISP.background_jobs')) {
			$this->Job = ClassRegistry::init('Job');
			$this->Job->id = $jobId;
		}
		if ($proposalCount > 0) {
			foreach ($proposals as $k => $proposal) {
				$this->__afterSaveCorrelation($proposal['ShadowAttribute']);
				if ($jobId && Configure::read('MISP.background_jobs') && $k > 0 && $proposalCount % $k == 10) {
					$this->Job->saveField('progress', ($k / $proposalCount * 100));
				} 
			}
		}
		if ($jobId && Configure::read('MISP.background_jobs')) {
			$this->Job->saveField('progress', 100);
			$this->Job->saveField('status', 4);
			$this->Job->saveField('message', 'Job done.');
		}
		return $proposalCount;
	}
	
	public function upgradeToProposalCorrelation() {
		$this->Log = ClassRegistry::init('Log');
		if (!Configure::read('MISP.background_jobs')) {
			$this->Log->create();
			$this->Log->save(array(
					'org' => 'SYSTEM',
					'model' => 'Server',
					'model_id' => 0,
					'email' => 'SYSTEM',
					'action' => 'update_database',
					'user_id' => 0,
					'title' => 'Starting proposal correlation generation',
					'change' => 'The generation of Proposal correlations as part of the 2.4.20 datamodel upgrade has started'
			));
			$count = $this->generateCorrelation();
			$this->Log->create();
			if (is_numeric($count)) {
				$this->Log->save(array(
						'org' => 'SYSTEM',
						'model' => 'Server',
						'model_id' => 0,
						'email' => 'SYSTEM',
						'action' => 'update_database',
						'user_id' => 0,
						'title' => 'Proposal correlation generation complete',
						'change' => 'The generation of Proposal correlations as part of the 2.4.20 datamodel upgrade is completed. ' . $count . ' proposals used.'
				));
			} else {
				$this->Log->save(array(
						'org' => 'SYSTEM',
						'model' => 'Server',
						'model_id' => 0,
						'email' => 'SYSTEM',
						'action' => 'update_database',
						'user_id' => 0,
						'title' => 'Proposal correlation generation failed',
						'change' => 'The generation of Proposal correlations as part of the 2.4.20 has failed. You can rerun it from the administrative tools.'
				));
			}
		} else {
			$job = ClassRegistry::init('Job');
			$job->create();
			$data = array(
					'worker' => 'default',
					'job_type' => 'generate proposal correlation',
					'job_input' => 'All attributes',
					'retries' => 0,
					'status' => 1,
					'org' => 'SYSTEM',
					'message' => 'Correlating Proposals.',
			);
			$job->save($data);
			$jobId = $job->id;
			$process_id = CakeResque::enqueue(
					'default',
					'AdminShell',
					array('jobGenerateShadowAttributeCorrelation', $jobId)
			);
			$job->saveField('process_id', $process_id);
			$this->Log->create();
			$this->Log->save(array(
					'org' => 'SYSTEM',
					'model' => 'Server',
					'model_id' => 0,
					'email' => 'SYSTEM',
					'action' => 'update_database',
					'user_id' => 0,
					'title' => 'Proposal correlation generation job queued',
					'change' => 'The job for the generation of Proposal correlations as part of the 2.4.20 datamodel upgrade has been queued'
			));
		}
	}
}