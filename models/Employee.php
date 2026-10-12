<?php
    namespace app\models;
    
    use Yii;
    use yii\web\IdentityInterface;
    use yii\db\ActiveRecord;

    use yii\helpers\ArrayHelper;
    use yii\base\Model;
    use app\models\EmpEducation;
    use app\models\EmpLanguage;
    use app\models\Country;
    use app\models\State;
    use app\models\City;
    use app\models\EmpWork;
    use app\models\Gender ;
    use app\models\FoodPreference ;
    

    class Employee extends ActiveRecord implements IdentityInterface{

        public $ol;
        public $al;
        public $emp_higher_edu;
        public $emp_tech_knowledge;
        public $emp_language;
        public $emp_brief_code ;
        public $emp_work;
        public $salaryrange;
        public $emp_skill;
        public $emp_education;

         public static function tableName(){
            return  'YOUR_DATABASE_NAME';
        }

        public static function getDb()
        {
            return Yii::$app->dbAuth; // Points to the 'dbAuth' component
        }
        

        public function rules(){
            return [
                [['name','dob','mobile','email','password'],'required'],
                [['active_state'], 'safe'],
                ['name','string', 'max' => 100, 'tooLong'=> 'Name should not exceed 100 characters.'],   
                ['dob','compare','compareValue'=> date('Y-m-d'), 'operator'=>'<=', 'message'=>'Date of birth can not be a future date'],
                ['dob','compare','compareValue'=> date('Y-m-d', strtotime('-150 years')), 'operator'=>'>=', 'message'=>'Date of birth can not be more than 150 years ago'],
                ['dob','date','format'=> 'php:Y-m-d'],
                ['mobile','string', 'max' => 15, 'tooLong'=> 'Mobile number should not exceed 15 digits.'],   
                ['mobile', 'match', 'pattern' => '/^[0-9]{10}$/', 'message' => 'Invalid mobile number.'],
                ['password', 'string', 'min' => 8],
                ['password', 'string', 'max' => 100],
                [['email'],'email'],
                [['email'], 'unique', 'message' => 'This email is already in use.'],
                ['password', 'match', 'pattern' => '/([a-z].*){2,}/', 'message' => 'Password must contain at least 2 lower case characters.'],
                ['password', 'match', 'pattern' => '/([A-Z].*){1,}/', 'message' => 'Password must contain at least 1 upper case characters.'],
                ['password', 'match', 'pattern' => '/([0-9].*){2,}/', 'message' => 'Password must contain at least 2 numeric/digit characters.'],
                ['password', 'match', 'pattern' => '/([^a-zA-Z0-9].*){2,}/', 'message' => 'Password must contain at least 2 special characters.']
            ];
        }

        public function getId() {
            return $this->emp_id; 
        }

        // Required by IdentityInterface: Finds a user by their ID (Primary Key)
        public static function findIdentity($id){
            return static::findOne($id);
        }

        // Required by IdentityInterface: Used for "Remember Me" cookies
        public function getAuthKey(){
            return $this->auth_key; // Ensure you have an 'auth_key' column in your table
        }

        public function validateAuthKey($authKey){
            return $this->getAuthKey() === $authKey;
        }

        // Required by IdentityInterface: Leave this as is for now
        public static function findIdentityByAccessToken($token, $type = null){
            return null; 
        }

        public function attributeLabels(){
            return [
                'name' => 'Name',
                'dob' => 'Date of Birth',
                'mobile' => 'Mobile Number',
                'email' => 'Email',                           
            ];

        }

    public function afterFind()
        {
            parent::afterFind();
            
            $this->ol = EmpEducation::findOne(['emp_id' => $this->emp_id, 'edu_id' => 1]) ?? new EmpEducation();
            $this->al = EmpEducation::findOne(['emp_id' => $this->emp_id, 'edu_id' => 2]) ?? new EmpEducation();
            $this->emp_higher_edu = $this->empHigherEdu ?? new EmpHigherEdu();

            $this->emp_brief_code = $this->empBriefCode ?? new EmpBriefCode();
            $this->emp_work = $this->empWork ?? new EmpWork();

            // 2. Many-to-Many / HasMany collections
            // You can index these by the foreign key (e.g., lang_id or tech_id) 
            // to make it easy to access in your view loop.
            $this->emp_language = ArrayHelper::index(
                $this->empLanguage, 'language_id'
            );
            
            $this->emp_tech_knowledge = ArrayHelper::index(
                $this->empTech, 
                'tech_id'
            );
            $this->emp_skill = ArrayHelper::index(
                $this->empSkill, 'skill_id'
            );
            
        }


    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // Only hash if it's a new record or the password attribute was actually changed
            if ($this->isAttributeChanged('password') || $insert) {
                if (!empty($this->password)) {
                    $this->password = Yii::$app->getSecurity()->generatePasswordHash($this->password);
                }
            }
            
            // IMPORTANT: Generate auth_key if it's empty
            if (empty($this->auth_key)) {
                $this->auth_key = Yii::$app->security->generateRandomString();
            }
            
            return true;
        }
        return false;
    }

 }

?>