BROKER_URL = 'amqp://guest:guest@localhost:5672/'
CELERY_RESULT_BACKEND = 'amqp'
CELERY_TASK_SERIALIZER='json'
CELERY_RESULT_SERIALIZER='json'
CELERY_TASK_RESULT_EXPIRES=900 # 15 min

from celery.schedules import crontab
from datetime import timedelta

CELERYBEAT_SCHEDULE = {
    # 252 times a day
    'every-5-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=5),
        'args': ([5]),
    },
    # 126 times a day
    'every-10-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=10),
        'args': ([10]),
    },
    # 42 times a day
    'every-30-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=30),
        'args': ([30]),
    },
    # 24 times a day
    'every-60-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=60),
        'args': ([60]),
    },
    # 8 times a day
    'every-180-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=180),
        'args': ([180]),
    },
    # 4 times a day
    'every-360-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=360),
        'args': ([360]),
    },
    # twice a day
    'every-720-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=720),
        'args': ([720]),
    },
    # once a day
    'every-1440-minutes': {
        'task': 'cron.process',
        'schedule': timedelta(minutes=1440),
        'args': ([1440]),
    },
}
