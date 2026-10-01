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

        DEPLOYED = "false"
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

                    echo "Commit:"
                    git rev-parse HEAD

                    echo ""
                    echo "Files:"
                    find . -maxdepth 2 -type f | sort
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

                    PHP_COUNT=$(find "${WORKSPACE}" \
                        -type f \
                        -name "*.php" \
                        -not -path "${WORKSPACE}/vendor/*" \
                        -not -path "${WORKSPACE}@tmp/*" \
                        | wc -l)

                    echo "PHP files found: ${PHP_COUNT}"

                    if [ "${PHP_COUNT}" -eq 0 ]; then

                        echo "No PHP files found."

                    else

                        find "${WORKSPACE}" \
                            -type f \
                            -name "*.php" \
                            -not -path "${WORKSPACE}/vendor/*" \
                            -not -path "${WORKSPACE}@tmp/*" \
                            -print0 |
                        xargs -0 -n1 php -l

                    fi

                    echo ""
                    echo "================================"
                    echo "ALL PHP FILES PASSED"
                    echo "================================"
                '''
            }
        }


        stage('Check Selenium Environment') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "CHECKING SELENIUM ENVIRONMENT"
                    echo "================================"

                    echo "Python:"
                    ${PYTHON} --version

                    echo ""
                    echo "Selenium:"

                    ${PYTHON} -c \
                        "import selenium; print(selenium.__version__)"

                    echo ""
                    echo "Chrome/Chromium:"

                    chromium --version

                    echo ""
                    echo "Selenium environment OK."
                '''
            }
        }


        stage('Backup Current Website') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "BACKING UP CURRENT WEBSITE"
                    echo "================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    sudo rm -rf \
                        "${BACKUP_DIR}/current"

                    sudo mkdir -p \
                        "${BACKUP_DIR}/current"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/current/"

                    echo ""
                    echo "Backup completed."

                    echo ""
                    echo "Backup contents:"

                    sudo find \
                        "${BACKUP_DIR}/current" \
                        -maxdepth 2 \
                        -type f \
                        | head -50
                '''
            }
        }


        stage('Deploy to /var/www/html') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "DEPLOYING TO /var/www/html"
                    echo "================================"

                    sudo rsync -a \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo ""
                    echo "Deployment completed."
                '''

                script {
                    env.DEPLOYED = "true"
                }
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

                    HTTP_CODE=$(curl \
                        --output /dev/null \
                        --silent \
                        --show-error \
                        --write-out "%{http_code}" \
                        http://127.0.0.1/)

                    echo "HTTP status: ${HTTP_CODE}"

                    if [ "${HTTP_CODE}" -lt 200 ] || [ "${HTTP_CODE}" -ge 400 ]; then

                        echo "HTTP TEST FAILED."

                        exit 1

                    fi

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
                    echo "================================"
                    echo "SELENIUM TEST PASSED"
                    echo "================================"
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

            sh '''
                echo "Current website:"
                ls -la "${WEB_DIR}"

                echo ""
                echo "Deployment completed successfully."
            '''
        }


        failure {

            script {

                if (env.DEPLOYED == "true") {

                    echo '''
========================================
DEPLOYMENT FAILED
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

                            echo ""
                            echo "================================"
                            echo "NO BACKUP AVAILABLE"
                            echo "================================"

                        fi
                    '''

                } else {

                    echo '''
========================================
BUILD FAILED BEFORE DEPLOYMENT
NO ROLLBACK REQUIRED
========================================
'''
                }
            }
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
