<?php

namespace app\modules\employee\controllers;

use Yii;

use app\models\Employee;
use app\modules\employee\models\UserSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use app\models\EmpEducation;
use app\models\EmpTechnical;
use app\models\EmpBriefCode;
use app\models\EmpHigherEdu;
use app\models\EmpLanguage;
use app\models\EmpWork;
use app\models\EmpSkill;

/**
 * UserController implements the CRUD actions for Employee model.
 */
class UserController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all Employee models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Employee model.
     * @param int $emp_id
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($emp_id)
    {
        return $this->render('view', [
            'model' => $this->findModel($emp_id),
        ]);
    }

    /**
     * Creates a new Employee model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Employee();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                return $this->redirect(['view', 'emp_id' => $model->emp_id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Employee model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $emp_id
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($emp_id)
    {
        $model = Employee::find()->where(['emp_id' => $emp_id]) ->with(['gender', 'country', 'city', 'empLanguage','empBriefCode','empHigherEdu','empEducation','empSkill','empWork','empTech'])->one(); 
        
        $model->ol = EmpEducation::findOne(['emp_id' => $emp_id, 'edu_id' => '1']) ?? new EmpEducation();
        $model->al = EmpEducation::findOne(['emp_id' => $emp_id, 'edu_id' => '2']) ?? new EmpEducation();
        $model->emp_higher_edu = EmpHigherEdu::findOne(['emp_id' => $emp_id]) ?? new EmpHigherEdu();
        $model->emp_work = EmpWork::findOne(['emp_id' => $emp_id]) ?? new EmpWork();

        $model->emp_tech_knowledge = EmpTechnical::find()->where(['emp_id' => $emp_id])->all()? : new EmpTechnical();
        $model->emp_language = EmpLanguage::find()->where(['emp_id' => $emp_id])->all()? : new EmpLanguage();
        $model->emp_brief_code = $model->empBriefCode ?? new EmpBriefCode();
        $model->emp_skill = EmpSkill::find()->where(['emp_id' => $emp_id])->all()? : new EmpSkill();

        if ($model->emp_work && $model->emp_work->start_date && $model->emp_work->end_date) {
        // Format MUST be "YYYY-MM-DD - YYYY-MM-DD" to be recognized
        $model->emp_work->date_range = $model->emp_work->start_date . ' - ' . $model->emp_work->end_date;
    }

        $this->findModel($emp_id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
                    // Load data into related models
                
                    $model->ol->attributes = Yii::$app->request->post()['EmpEducation'][0];
                    $ol = $model->ol;
                    $ol->emp_id = $model->emp_id;
                    $ol->edu_id = 1; // 1 is the ID for O/L educsation
                       
                    
                    $model->al->attributes = Yii::$app->request->post()['EmpEducation'][1];
                    $al = $model->al;
                    $al->emp_id = $model->emp_id;
                    $al->edu_id = 2; // 2 is the ID for A/L education
                    
                    // Load data into EmpHigherEdu model
                    $model->emp_higher_edu->attributes = Yii::$app->request->post('EmpHigherEdu');
                    $emp_higher_edu = $model->emp_higher_edu;
                    $emp_higher_edu->emp_id = $model->emp_id;
                        

                
                    // Load data into EmpTechSkill model
                        foreach (Yii::$app->request->post('EmpTechnical', []) as $i => $techData) {
                            // Create a NEW instance for each row of data
                            $techModel = new EmpTechnical();
                            
                            // Mass-assign the data (value, tech_id, etc.)
                            $techModel->attributes = $techData;
                            
                            // Assign the parent ID
                            $techModel->emp_id = $model->emp_id;
                            
                            if (!$techModel->save()) {
                                // Optional: Log errors if save fails
                                Yii::error($techModel->getErrors());
                            }
                        }

                    // Load data into EmpSkill model
                    foreach (Yii::$app->request->post('EmpSkill', []) as $i => $skillhData) {
          
                        $skillModel = new EmpSkill();
                        
                        
                        $skillModel->attributes = $skillhData;
                        
                      
                        $skillModel->emp_id = $model->emp_id;
                        
                        if (!$skillModel->save()) {
                            Yii::error($skillModel->getErrors());
                        }
                    }
                    // Load data into EmpLanguage model
                     foreach (Yii::$app->request->post('EmpLanguage', []) as $i => $languageData) {
          
                        $languageModel = new EmpSkill();
                        
                        
                        $languageModel->attributes = $languageData;
                        
                      
                        $languageModel->emp_id = $model->emp_id;
                        
                        if (!$skillModel->save()) {
                            Yii::error($skillModel->getErrors());
                        }
                    }

                    // Load data into EmpBriefCode model
                    $model->emp_brief_code->attributes = Yii::$app->request->post('EmpBriefCode');
                    $briefCodeModel = new EmpBriefCode();           
                    $briefCodeModel->attributes = $model->emp_brief_code->attributes;
                    $briefCodeModel->emp_id = $model->emp_id;  

                    // Load data into EmpBriefCode model
                    $model->emp_work->attributes = Yii::$app->request->post('EmpWork');
                    $empWork = new EmpWork();           
                    $empWork->attributes = $model->emp_work->attributes;
                    $empWork->emp_id = $model->emp_id; 
                    
                    if($ol->save()  && $al->save() && $emp_higher_edu->save() && $briefCodeModel->save() && $empWork->save()){
                        Yii::$app->session->setFlash('success', 'Employee created successfully!');
                        $this->redirect(['view', 'emp_id' => $model->emp_id]);
                    } 
                    
                    else {
                        $errorString = \yii\helpers\Html::errorSummary($empWork);                
                        Yii::$app->session->setFlash('error', 'EmpWork validation failed: ' . $errorString);
                    } 
        }

        return $this->render('update', [
            'model' => $model,'ol'=>$model->ol,'al'=>$model->al,'emp_higher_edu'=>$model->emp_higher_edu, 'emp_tech_knowledge'=>$model->emp_tech_knowledge 
        ]);
    }

    /**
     * Deletes an existing Employee model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $emp_id
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($emp_id)
    {
        $this->findModel($emp_id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Employee model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $emp_id
     * @return Employee the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($emp_id)
    {
        if (($model = Employee::findOne(['emp_id' => $emp_id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
