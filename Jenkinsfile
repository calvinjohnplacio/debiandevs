pipeline {

    agent any

    options {

        timestamps()

        disableConcurrentBuilds()

        timeout(
            time: 20,
            unit: 'MINUTES'
        )
    }

    environment {

        WEB_DIR = "/var/www/html"

        BACKUP_DIR = "/var/backups/myapp"

        PYTHON = "/opt/selenium-venv/bin/python"
    }


    stages {


        stage('Checkout') {

            steps {

                git(
                    url: 'https://github.com/calvinjohnplacio/debiandevs.git',
                    branch: 'main',
                    credentialsId: 'github-pat'
                )

                sh '''
                    echo "================================"
                    echo "CHECKOUT"
                    echo "================================"

                    git rev-parse HEAD
                '''
            }
        }


       stage('Check PHP Syntax') {

    steps {

        sh '''
            set -e

            echo "================================"
            echo "CHECKING ALL PHP FILES"
            echo "================================"

            find "${WORKSPACE}" \
                -type f \
                -name "*.php" \
                -not -path "${WORKSPACE}/vendor/*" \
                -not -path "${WORKSPACE}@tmp/*" \
                -print0 |
            xargs -0 -n1 php -l

            echo ""
            echo "================================"
            echo "ALL PHP FILES PASSED"
            echo "================================"
        '''
    }
}



        stage('Check Python Selenium') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "CHECKING SELENIUM"
                    echo "================================"

                    ${PYTHON} --version

                    ${PYTHON} -c \
                        "import selenium; print('Selenium:', selenium.__version__)"

                    echo "Selenium is ready."
                '''
            }
        }


        stage('Backup Current Website') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "BACKUP"
                    echo "================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    sudo rm -rf \
                        "${BACKUP_DIR}/current"

                    sudo mkdir -p \
                        "${BACKUP_DIR}/current"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/current/"

                    echo "Backup completed."
                '''
            }
        }


        stage('Deploy to /var/www/html') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "DEPLOYING"
                    echo "================================"

                    sudo rsync -a \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo "Deployment completed."
                '''
            }
        }


        stage('HTTP Test') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "HTTP TEST"
                    echo "================================"

                    sleep 2

                    curl \
                        --fail \
                        --silent \
                        --show-error \
                        http://127.0.0.1/

                    echo ""

                    echo "HTTP TEST PASSED."
                '''
            }
        }


        stage('Python Selenium Test') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "PYTHON SELENIUM TEST"
                    echo "================================"

                    ${PYTHON} \
                        "${WORKSPACE}/tests/selenium_test.py"

                    echo ""
                    echo "SELENIUM TEST PASSED."
                '''
            }
        }
    }


    post {


        success {

            echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================
'''
        }


        failure {

            echo '''
========================================
PIPELINE FAILED
ROLLING BACK
========================================
'''

            sh '''
                set +e

                if [ -d "${BACKUP_DIR}/current" ]; then

                    echo "Restoring previous website..."

                    sudo rsync -a \
                        --delete \
                        "${BACKUP_DIR}/current/" \
                        "${WEB_DIR}/"

                    echo ""
                    echo "================================"
                    echo "ROLLBACK COMPLETED"
                    echo "================================"

                else

                    echo "================================"
                    echo "NO BACKUP AVAILABLE"
                    echo "================================"

                fi
            '''
        }


        always {

            echo '''
========================================
JENKINS BUILD FINISHED
========================================
'''
        }
    }
}
